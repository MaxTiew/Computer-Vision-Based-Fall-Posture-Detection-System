import cv2
import mediapipe as mp
import numpy as np
import sounddevice as sd
import vosk
import queue
import json
import threading
import time
import math
import os
import signal
import requests
import torch
from ultralytics import YOLO
from collections import deque
from datetime import datetime
try:
    from zoneinfo import ZoneInfo
except ImportError:
    # Simple fallback for Python < 3.9
    from datetime import timezone, timedelta
    ZoneInfo = lambda x: timezone(timedelta(hours=8))
MY_TZ = ZoneInfo("Asia/Kuala_Lumpur")
from flask import Flask, Response, jsonify, request
from flask_cors import CORS

# Optional dependencies for voice prompt
try:
    import pyttsx3
except ImportError:
    pyttsx3 = None

try:
    import pygame
    pygame.mixer.init()
    pygame_available = True
except Exception as e:
    print(f"Pygame/Audio init failed: {e}")
    pygame_available = False

# Set working directory to script location
os.chdir(os.path.dirname(os.path.abspath(__file__)))
def _m(n): return os.path.join(os.path.dirname(os.path.abspath(__file__)), n)

# ===================
# CONFIGURATION
# ===================
WEBHOOK_BASE_URL = "http://localhost/goodlife/" 

app = Flask(__name__)
CORS(app) 

# ===================
# GLOBAL STATE
# ===================
system_state = {
    "locked": False,
    "message": "",
    "mode": "NORMAL"
}

# Session Data
current_elder_id = None
session_start_time = None
camera_mode = "single"
camera1_choice = None
camera2_choice = None
resolved_camera1_idx = None
resolved_camera2_idx = None
session_video_path_web = None
session_video_path_local = None
session_writer = None
session_video_path_web2 = None
session_video_path_local2 = None
session_writer2 = None

# State Machine: "NORMAL", "GRACE", "RECORDING", "LOCKED"
machine_state = "NORMAL"
current_event_id = None
grace_start_time = None
record_start_time = None
event_writer = None
event_writer2 = None
event_video_path = None
event_video_path_web2 = None
current_alert_types = []

# Persistent Processing Flags
latest_frame = None
latest_frame2 = None
is_monitoring = False
exit_flag = False  # For graceful termination
monitor_thread = None
monitoring_startup_ready = False
monitoring_startup_error = None

# ===================
# SENSITIVITY SETTINGS (DYNAMIC)
# ===================
SETTINGS_FILE = "settings.json"
settings = {
    "RELATIVE_SLUMP_THRESHOLD": 0.15,
    "TILT_THRESHOLD_DEG": 45,
    "EYE_SQUINT_THRESHOLD": 0.02,
    "BROW_FURROW_THRESHOLD": 0.18,
    "HAND_HOLD_THRESHOLD": 1.5,
    "SLUMP_HOLD_DURATION": 2.0,
    "TILT_HOLD_DURATION": 1.5,
    "PAIN_HOLD_DURATION": 2.0,
    "FALL_HOLD_DURATION": 5.0,
    "GRACE_PERIOD_DURATION": 10.0,
    "KEYWORDS": ["help", "ah", "ahh", "ouch", "ow", "pain", "emergency", "stop", "doctor", "hurt"],
    "GRACE_PROMPT_TEXT": "Are you okay? Please show thumbs up if you are safe.",
    "GRACE_PROMPT_AUDIO": ""
}

def load_settings():
    global settings
    if os.path.exists(SETTINGS_FILE):
        try:
            with open(SETTINGS_FILE, "r") as f:
                new_settings = json.load(f)
                settings.update(new_settings)
        except Exception as e:
            print(f"Error loading settings: {e}")

def settings_reloader():
    while not exit_flag:
        load_settings()
        time.sleep(5)

load_settings()
threading.Thread(target=settings_reloader, daemon=True).start()

# ===================
# VOICE PROMPT LOGIC
# ===================
voice_prompt_lock = threading.Lock()

def play_grace_prompt():
    """Plays a voice prompt once in a background thread."""
    def _play_task():
        if not voice_prompt_lock.acquire(blocking=False):
            return # Already playing
        
        try:
            audio_path = settings.get("GRACE_PROMPT_AUDIO", "")
            prompt_text = settings.get("GRACE_PROMPT_TEXT", "Are you okay? Please show thumbs up if you are safe.")
            
            # 1. Try playing custom audio file
            if audio_path:
                full_path = _m(audio_path)
                if os.path.exists(full_path) and pygame:
                    try:
                        print(f"Playing custom grace prompt audio: {audio_path}")
                        pygame.mixer.music.load(full_path)
                        pygame.mixer.music.play()
                        while pygame.mixer.music.get_busy():
                            time.sleep(0.1)
                        return
                    except Exception as e:
                        print(f"Grace prompt audio failed, falling back to TTS: {e}")
            
            # 2. Fallback to TTS
            if pyttsx3:
                try:
                    print(f"Speaking grace prompt text: {prompt_text}")
                    engine = pyttsx3.init()
                    engine.say(prompt_text)
                    engine.runAndWait()
                    # engine.stop() # Some engines need this to avoid issues on next call
                except Exception as e:
                    print(f"TTS engine failed: {e}")
            else:
                print(f"WARNING: No audio file and pyttsx3 not installed. Prompt text: {prompt_text}")
                
        finally:
            voice_prompt_lock.release()

    threading.Thread(target=_play_task, daemon=True).start()

# ===================
# AUDIO SETUP
# ===================
audio_stream = None
try:
    model = vosk.Model("vosk-model-small-en-us-0.15")
    audio_q = queue.Queue()
    voice_alert = False

    def audio_callback(indata, frames, time_info, status):
        audio_q.put(bytes(indata))

    def speech_worker():
        global voice_alert
        rec = vosk.KaldiRecognizer(model, 16000)
        while not exit_flag:
            try:
                data = audio_q.get(timeout=1)
                if rec.AcceptWaveform(data):
                    result = json.loads(rec.Result())
                    text = result.get("text", "")
                    if text:
                        print("Recognized:", text)
                        for word in settings["KEYWORDS"]:
                            if word in text.lower():
                                voice_alert = True
            except queue.Empty:
                continue
            except Exception as e:
                print(f"Speech worker error: {e}")
                break

    audio_stream = sd.RawInputStream(samplerate=16000, blocksize=8000, dtype='int16', channels=1, callback=audio_callback)
    audio_stream.start()
    threading.Thread(target=speech_worker, daemon=True).start()
    print("Vosk Audio System Initialized.")
except Exception as e:
    print(f"Error initializing audio: {e}")

# ===================
# MEDIAPIPE SETUP
# ===================
print("Initializing Mediapipe...")
try:
    mp_face = mp.solutions.face_mesh
    face_mesh = mp_face.FaceMesh(max_num_faces=1, refine_landmarks=True)
    mp_pose = mp.solutions.pose
    pose = mp_pose.Pose(min_detection_confidence=0.5, min_tracking_confidence=0.5)
    mp_hands = mp.solutions.hands
    hands = mp_hands.Hands(max_num_hands=1, min_detection_confidence=0.7)
    print("Mediapipe Initialized.")
except Exception as e:
    print(f"Error initializing Mediapipe: {e}")
    exit(1)

# ===================
# FALL DETECTION SETUP
# ===================
print("Initializing Fall Detection (YOLO)...")
try:
    yolo_model = YOLO(_m("yolov8n.pt"))
    BED_CLASS = 59
    # We will use existing mp_pose Solutions instead of vision.PoseLandmarker
    # to avoid resource conflicts and performance lag.
    print("Fall Detection Initialized.")
except Exception as e:
    print(f"Error initializing Fall Detection: {e}")

# Helpers
def dist(a, b): return np.linalg.norm(np.array(a) - np.array(b))
def p(lm, idx, w, h): return (int(lm[idx].x * w), int(lm[idx].y * h))
def get_coords(landmarks, idx, w, h):
    if landmarks[idx].visibility < 0.2: return None
    return np.array([landmarks[idx].x * w, landmarks[idx].y * h])
def calculate_tilt(p1, p2):
    dy = p2[1] - p1[1]
    dx = p2[0] - p1[0]
    return abs(math.degrees(math.atan2(dy, dx)))

def draw_progress_bar(frame, x, y, start_time, duration, color, label):
    if start_time is None: return False
    elapsed = time.time() - start_time
    progress = min(elapsed / duration, 1.0)
    bar_w, bar_h = 100, 10
    x = max(0, min(x, frame.shape[1] - bar_w))
    y = max(20, min(y, frame.shape[0] - bar_h))
    cv2.rectangle(frame, (x, y), (x + bar_w, y + bar_h), (50, 50, 50), -1)
    cv2.rectangle(frame, (x, y), (x + int(bar_w * progress), y + bar_h), color, -1)
    cv2.putText(frame, f"{label} {int(progress*100)}%", (x, y - 5), cv2.FONT_HERSHEY_PLAIN, 1, color, 1)
    return progress >= 1.0

def is_thumbs_up(hand_landmarks):
    lm = hand_landmarks.landmark
    def get_dist(idx1, idx2): return math.hypot(lm[idx1].x - lm[idx2].x, lm[idx1].y - lm[idx2].y)
    
    # 1. Thumb Tip (4) must be significantly higher than Thumb IP (3) and Index MCP (5)
    thumb_up = lm[4].y < lm[3].y - 0.01 and lm[4].y < lm[5].y - 0.02
    
    # 2. Other fingers (8, 12, 16, 20) must be curled
    # Tip should be lower than PIP and closer to wrist than PIP
    fingers_curled = True
    for tip, pip, mcp in [(8, 6, 5), (12, 10, 9), (16, 14, 13), (20, 18, 17)]:
        if lm[tip].y < lm[pip].y or get_dist(tip, 0) > get_dist(pip, 0):
            fingers_curled = False
            break
            
    # 3. Thumb must be extended (tip further from wrist than IP)
    thumb_extended = get_dist(4, 0) > get_dist(3, 0) + 0.01
    
    return thumb_up and fingers_curled and thumb_extended

# ===================
# FALL DETECTION CLASSES
# ===================
class FallDetector:
    def __init__(self):
        self.position_history = deque(maxlen=30)
        self.velocity_history = deque(maxlen=10)
        self.posture_history  = deque(maxlen=15)
        self.fall_threshold_velocity = -0.08

    def update(self, center_y, posture, timestamp):
        self.position_history.append((timestamp, center_y))
        self.posture_history.append(posture)
        if len(self.position_history) >= 2:
            t1, y1 = self.position_history[-2]
            t2, y2 = self.position_history[-1]
            dt = t2 - t1
            if dt > 0:
                self.velocity_history.append((y2 - y1) / dt)

    def detect_fall(self):
        if len(self.velocity_history) < 5:
            return False, 0
        recent = list(self.velocity_history)[-5:]
        rapid_descent = sum(1 for v in recent if v > abs(self.fall_threshold_velocity))
        if len(self.posture_history) >= 10:
            postures = list(self.posture_history)[-10:]
            was_upright = any(p == 'Upright' for p in postures[:5])
            is_lying    = postures[-1] == 'Lying Down'
            if was_upright and is_lying and rapid_descent >= 3:
                return True, rapid_descent
        return False, rapid_descent

class BedTracker:
    EROSION_PX = 25
    CONFIRM_FRAMES = 5
    def __init__(self):
        self.beds = {}
        self.next_id = 0
        self.timeout = 2.5
        self._smooth = {}
        self._on_bed_counter = 0

    def update(self, detections, current_time):
        for bed in self.beds.values():
            bed['seen'] = False
        for bbox in detections:
            matched = False
            for bed_id, bed in self.beds.items():
                if self._iou(bbox, bed['bbox']) > 0.4:
                    smoothed = self._smooth_bbox(bed_id, bbox)
                    bed['bbox'] = smoothed
                    bed['last_seen'] = current_time
                    bed['seen'] = True
                    matched = True
                    break
            if not matched:
                self.beds[self.next_id] = {
                    'bbox': bbox, 'last_seen': current_time, 'seen': True
                }
                self._smooth[self.next_id] = list(bbox)
                self.next_id += 1
        to_del = [k for k, v in self.beds.items() if current_time - v['last_seen'] > self.timeout]
        for k in to_del:
            del self.beds[k]
            self._smooth.pop(k, None)

    def is_person_on_bed(self, person_bbox):
        px1, py1, px2, py2 = person_bbox
        cx = (px1 + px2) / 2
        head_y  = py1 + (py2 - py1) * 0.10
        lower_y = py1 + (py2 - py1) * 0.85
        raw_on_bed = False
        for bed in self.beds.values():
            ex1, ey1, ex2, ey2 = self._eroded(bed['bbox'])
            if ex2 <= ex1 or ey2 <= ey1: continue
            head_inside = (ex1 <= cx <= ex2 and ey1 <= head_y <= ey2)
            lower_inside = (ex1 <= cx <= ex2 and ey1 <= lower_y <= ey2)
            if head_inside and lower_inside:
                raw_on_bed = True
                break
        if raw_on_bed: self._on_bed_counter = min(self._on_bed_counter + 1, self.CONFIRM_FRAMES)
        else: self._on_bed_counter = max(self._on_bed_counter - 2, 0)
        return self._on_bed_counter >= self.CONFIRM_FRAMES

    def _iou(self, a, b):
        xi1, yi1 = max(a[0], b[0]), max(a[1], b[1])
        xi2, yi2 = min(a[2], b[2]), min(a[3], b[3])
        inter = max(0, xi2 - xi1) * max(0, yi2 - yi1)
        ua = (a[2]-a[0])*(a[3]-a[1]) + (b[2]-b[0])*(b[3]-b[1]) - inter
        return inter / ua if ua > 0 else 0

    def _smooth_bbox(self, bed_id, new_bbox, alpha=0.25):
        if bed_id not in self._smooth: self._smooth[bed_id] = list(new_bbox)
        s = self._smooth[bed_id]
        self._smooth[bed_id] = [int(alpha * new_bbox[i] + (1 - alpha) * s[i]) for i in range(4)]
        return tuple(self._smooth[bed_id])

    def _eroded(self, bbox):
        x1, y1, x2, y2 = bbox
        e = self.EROSION_PX
        return (x1 + e, y1 + e, x2 - e, y2 - e)

def calculate_metrics_fall(landmarks, bbox):
    metrics = {}
    nose, ls, rs = landmarks[0], landmarks[11], landmarks[12]
    lh, rh = landmarks[23], landmarks[24]
    smx, smy = (ls.x + rs.x) / 2, (ls.y + rs.y) / 2
    hmx, hmy = (lh.x + rh.x) / 2, (lh.y + rh.y) / 2
    dx, dy = hmx - smx, hmy - smy
    metrics['torso_angle'] = abs(math.degrees(math.atan2(dx, dy)))
    metrics['shoulder_hip_distance'] = abs(smy - hmy)
    metrics['head_hip_distance'] = abs(nose.y - hmy)
    bh, bw = abs(nose.y - hmy), abs(ls.x - rs.x)
    metrics['body_orientation_ratio'] = bh / bw if bw > 0 else 999
    x1, y1, x2, y2 = bbox
    bboxh, bboxw = y2 - y1, x2 - x1
    metrics['aspect_ratio'] = bboxh / bboxw if bboxw > 0 else 999
    return metrics

def detect_posture_binary(bbox, landmarks, on_bed=False):
    metrics = calculate_metrics_fall(landmarks, bbox)
    up, ly = 0, 0
    if on_bed: ly += 3
    ar = metrics['aspect_ratio']
    if ar > 1.5: up += 4
    elif ar > 1.2: up += 2
    elif ar < 1.0: ly += 4
    elif ar < 1.2: ly += 2
    ta = metrics['torso_angle']
    if ta < 30: up += 4
    elif ta < 45: up += 2
    elif ta > 60: ly += 4
    elif ta > 45: ly += 2
    sh = metrics['shoulder_hip_distance']
    if sh > 0.15: up += 2
    elif sh < 0.05: ly += 2
    hh = metrics['head_hip_distance']
    if hh > 0.25: up += 2
    elif hh < 0.1: ly += 2
    bo = metrics['body_orientation_ratio']
    if bo > 2.0: up += 2
    elif bo < 1.5: ly += 2
    scores = {'Upright': up, 'Lying Down': ly}
    final = max(scores, key=scores.get)
    total = sum(scores.values())
    conf = int((scores[final] / total) * 100) if total > 0 else 50
    return final, conf, metrics

import subprocess

def get_camera_mapping():
    """
    Attempts to map camera names to indices using pygrabber (DirectShow).
    Returns a dictionary mapping 'laptop', 'external', 'phone' to their identified index.
    """
    mapping = {'laptop': 0, 'external': 1, 'phone': 2} # Defaults
    try:
        from pygrabber.dshow_graph import FilterGraph
        graph = FilterGraph()
        devices = graph.get_input_devices()
        
        print(f"Detected Cameras (DirectShow): {devices}")
        
        laptop_idx = -1
        external_idx = -1
        phone_idx = -1
        
        for i, name in enumerate(devices):
            name_lower = name.lower()
            # Laptop keywords
            if any(k in name_lower for k in ['acer', 'integrated', 'built-in', 'front', 'user facing', 'facetime']):
                if laptop_idx == -1: laptop_idx = i
            # Phone keywords (DroidCam is very common)
            elif any(k in name_lower for k in ['droidcam', 'iriun', 'epoccam', 'phone']):
                if phone_idx == -1: phone_idx = i
            # External keywords (Logitech, webcam, etc.)
            elif any(k in name_lower for k in ['logi', 'c270', 'webcam', 'usb video', 'usb camera', 'external']):
                if external_idx == -1: external_idx = i
        
        # If we have multiple but some weren't found, try to guess
        # (e.g., if only two cameras and one is laptop, the other is likely external)
        if laptop_idx != -1: mapping['laptop'] = laptop_idx
        if external_idx != -1: mapping['external'] = external_idx
        if phone_idx != -1: mapping['phone'] = phone_idx
        
        # Refinement: if external wasn't found but there's an unknown device at index 0 or 1
        if external_idx == -1:
            for i in range(len(devices)):
                if i != laptop_idx and i != phone_idx:
                    mapping['external'] = i
                    break
                    
        print(f"Guessed Mapping: {mapping}")
    except Exception as e:
        print(f"Error getting camera mapping with pygrabber: {e}")
        # Fallback to simple indices if pygrabber fails
    
    return mapping

# ===================
# MONITORING THREAD
# ===================
def resolve_camera_choice(choice, mapping, default_choice=None):
    candidate = choice if choice not in (None, "") else default_choice
    if candidate in (None, ""):
        candidate = settings.get("CAMERA_INDEX", 0)

    if isinstance(candidate, str):
        candidate = candidate.strip().lower()
        if candidate.startswith("role:"):
            role_name = candidate.split(":", 1)[1]
            if role_name in mapping:
                return mapping[role_name]
            raise ValueError(f"Unknown camera role '{role_name}'.")
        if candidate in mapping:
            return mapping[candidate]
        if candidate.startswith("raw:"):
            return int(candidate.split(":", 1)[1])
        try:
            candidate = int(candidate)
        except ValueError as exc:
            raise ValueError(f"Unsupported camera choice '{choice}'.") from exc

    if candidate == 0:
        return mapping['laptop']
    if candidate == 1:
        return mapping['external']
    if candidate == 2:
        return mapping['phone']
    return int(candidate)


def open_camera(index, label, allow_index_zero_fallback=False):
    opened_index = index
    cap = cv2.VideoCapture(index, cv2.CAP_DSHOW)
    if not cap.isOpened():
        cap = cv2.VideoCapture(index)

    if allow_index_zero_fallback and not cap.isOpened() and index != 0:
        print(f"{label} failed at index {index}. Falling back to index 0...")
        cap = cv2.VideoCapture(0, cv2.CAP_DSHOW)
        if not cap.isOpened():
            cap = cv2.VideoCapture(0)
        opened_index = 0

    if not cap.isOpened():
        raise RuntimeError(f"{label} failed to open at index {index}.")

    cap.set(cv2.CAP_PROP_FRAME_WIDTH, 640)
    cap.set(cv2.CAP_PROP_FRAME_HEIGHT, 480)
    return cap, opened_index


def create_camera_state():
    return {
        "bed_tracker": BedTracker(),
        "slump_start_time": None,
        "tilt_start_time": None,
        "pain_start_time": None,
        "hand_enter_time": None,
        "fall_start_time_sustained": None,
        "frame_count": 0,
        "last_person_bboxes": []
    }


def initialize_session_writers(frame1, frame2=None):
    global session_writer, session_writer2
    os.makedirs("../uploads/videos", exist_ok=True)
    fourcc = cv2.VideoWriter_fourcc(*'VP80')

    if current_elder_id and session_writer is None and frame1 is not None:
        h1, w1 = frame1.shape[:2]
        session_writer = cv2.VideoWriter(session_video_path_local, fourcc, 10.0, (w1, h1))

    if current_elder_id and camera_mode == "dual" and session_writer2 is None and frame2 is not None and session_video_path_local2:
        h2, w2 = frame2.shape[:2]
        session_writer2 = cv2.VideoWriter(session_video_path_local2, fourcc, 10.0, (w2, h2))


def start_event_recording(frame1, frame2=None):
    global record_start_time, event_writer, event_writer2, event_video_path, event_video_path_web2

    timestamp = datetime.now(MY_TZ).strftime("%Y%m%d_%H%M%S")
    fourcc = cv2.VideoWriter_fourcc(*'VP80')

    if camera_mode == "dual":
        event_video_path = f"uploads/videos/event_{current_elder_id}_{timestamp}_cam1.webm"
        event_video_path_web2 = f"uploads/videos/event_{current_elder_id}_{timestamp}_cam2.webm"
    else:
        event_video_path = f"uploads/videos/event_{current_elder_id}_{timestamp}.webm"
        event_video_path_web2 = None

    h1, w1 = frame1.shape[:2]
    event_writer = cv2.VideoWriter(f"../{event_video_path}", fourcc, 10.0, (w1, h1))
    event_writer2 = None

    if camera_mode == "dual" and frame2 is not None and event_video_path_web2:
        h2, w2 = frame2.shape[:2]
        event_writer2 = cv2.VideoWriter(f"../{event_video_path_web2}", fourcc, 10.0, (w2, h2))

    record_start_time = time.time()


def finalize_event_recording():
    global event_writer, event_writer2, current_event_id, machine_state

    if event_writer is not None:
        event_writer.release()
        event_writer = None
    if event_writer2 is not None:
        event_writer2.release()
        event_writer2 = None

    payload = {
        "elderID": current_elder_id,
        "eventType": " + ".join(current_alert_types),
        "videoPath": event_video_path
    }
    if event_video_path_web2:
        payload["videoPath2"] = event_video_path_web2

    try:
        response = requests.post(f"{WEBHOOK_BASE_URL}api_log_event.php", json=payload, timeout=2)
        resp_data = response.json()
        if resp_data.get("status") == "success":
            current_event_id = resp_data.get("eventID")
            try:
                requests.post(f"{WEBHOOK_BASE_URL}api_send_alert_email.php", json={"eventID": current_event_id}, timeout=5)
            except Exception as exc:
                print("Email webhook error:", exc)
    except Exception as exc:
        print("Webhook error:", exc)

    machine_state = "LOCKED"


def prepare_fall_tracking(frame, state):
    state["frame_count"] += 1

    if state["frame_count"] % 2 == 0:
        yolo_results = yolo_model(frame, conf=0.15, iou=0.45, classes=[0, BED_CLASS], verbose=False)
        person_bboxes, bed_bboxes = [], []
        for result in yolo_results:
            for box in result.boxes:
                cls = int(box.cls[0])
                yconf = float(box.conf[0])
                bbox = tuple(map(int, box.xyxy[0]))
                if cls == BED_CLASS and yconf >= 0.30:
                    bed_bboxes.append(bbox)
                elif cls == 0:
                    person_bboxes.append((bbox, yconf))
        person_bboxes.sort(key=lambda item: item[1], reverse=True)
        state["bed_tracker"].update(bed_bboxes, time.time())
        state["last_person_bboxes"] = person_bboxes
    else:
        person_bboxes = state["last_person_bboxes"]

    for bed in state["bed_tracker"].beds.values():
        bx1, by1, bx2, by2 = bed['bbox']
        cv2.rectangle(frame, (bx1, by1), (bx2, by2), (180, 80, 80), 1)

    return person_bboxes


def process_normal_frame(frame, rgb, state, person_bboxes):
    h, w, _ = frame.shape

    face_results = face_mesh.process(rgb)
    pose_results = pose.process(rgb)

    has_pose = pose_results.pose_landmarks is not None
    has_face = face_results.multi_face_landmarks is not None
    is_person_present = has_pose or has_face

    face_alert = False
    posture_alert = False
    body_alert = False
    fall_alert = False
    is_slumping = False
    is_tilting = False
    is_pain = False
    is_clutching = False
    posture_f = "Unknown"
    mode_label = None

    if not is_person_present:
        state["slump_start_time"] = None
        state["tilt_start_time"] = None
        state["pain_start_time"] = None
        state["hand_enter_time"] = None
        state["fall_start_time_sustained"] = None
        return [], mode_label, False

    hands_inside_box = False
    is_lying_down_solutions = False

    if has_pose:
        plm = pose_results.pose_landmarks.landmark
        l_shldr = get_coords(plm, 11, w, h)
        r_shldr = get_coords(plm, 12, w, h)
        l_hip = get_coords(plm, 23, w, h)
        r_hip = get_coords(plm, 24, w, h)
        nose_pose = get_coords(plm, 0, w, h)
        l_wrist = get_coords(plm, 15, w, h)
        r_wrist = get_coords(plm, 16, w, h)

        left_horizontal = l_shldr is not None and l_hip is not None and abs(l_shldr[0] - l_hip[0]) > abs(l_shldr[1] - l_hip[1])
        right_horizontal = r_shldr is not None and r_hip is not None and abs(r_shldr[0] - r_hip[0]) > abs(r_shldr[1] - r_hip[1])
        shoulders_vertical = l_shldr is not None and r_shldr is not None and abs(l_shldr[1] - r_shldr[1]) > abs(l_shldr[0] - r_shldr[0])
        if left_horizontal or right_horizontal or shoulders_vertical:
            is_lying_down_solutions = True

        if not is_lying_down_solutions and l_shldr is not None and r_shldr is not None and nose_pose is not None:
            shoulder_width = np.linalg.norm(l_shldr - r_shldr)
            midpoint_y = (l_shldr[1] + r_shldr[1]) / 2
            midpoint_x = int((l_shldr[0] + r_shldr[0]) / 2)
            if (midpoint_y - nose_pose[1]) < (shoulder_width * settings["RELATIVE_SLUMP_THRESHOLD"]):
                is_slumping = True
                if state["slump_start_time"] is None:
                    state["slump_start_time"] = time.time()
                if draw_progress_bar(frame, midpoint_x - 50, int(midpoint_y) - 60, state["slump_start_time"], settings["SLUMP_HOLD_DURATION"], (0, 0, 255), "SLUMP"):
                    posture_alert = True

        chest_center = None
        stomach_center = None
        proximity_threshold = 0

        if l_shldr is not None and r_shldr is not None and l_hip is not None and r_hip is not None:
            chest_center = (l_shldr + r_shldr) / 2
            mid_hip = (l_hip + r_hip) / 2
            stomach_center = chest_center + (mid_hip - chest_center) * 0.7
            proximity_threshold = dist(chest_center, mid_hip) * 0.3
        elif l_shldr is not None and l_hip is not None:
            chest_center = l_shldr + (l_hip - l_shldr) * 0.2
            stomach_center = l_shldr + (l_hip - l_shldr) * 0.7
            proximity_threshold = dist(l_shldr, l_hip) * 0.3
        elif r_shldr is not None and r_hip is not None:
            chest_center = r_shldr + (r_hip - r_shldr) * 0.2
            stomach_center = r_shldr + (r_hip - r_shldr) * 0.7
            proximity_threshold = dist(r_shldr, r_hip) * 0.3

        if chest_center is not None and stomach_center is not None and proximity_threshold > 0:
            d_left_chest = dist(l_wrist, chest_center) if l_wrist is not None else float('inf')
            d_right_chest = dist(r_wrist, chest_center) if r_wrist is not None else float('inf')
            d_left_stomach = dist(l_wrist, stomach_center) if l_wrist is not None else float('inf')
            d_right_stomach = dist(r_wrist, stomach_center) if r_wrist is not None else float('inf')

            cv2.circle(frame, (int(chest_center[0]), int(chest_center[1])), int(proximity_threshold), (0, 255, 255), 2)
            cv2.circle(frame, (int(stomach_center[0]), int(stomach_center[1])), int(proximity_threshold), (0, 165, 255), 2)

            if d_left_chest < proximity_threshold or d_right_chest < proximity_threshold or \
               d_left_stomach < proximity_threshold or d_right_stomach < proximity_threshold:
                is_clutching = True

        mp.solutions.drawing_utils.draw_landmarks(frame, pose_results.pose_landmarks, mp_pose.POSE_CONNECTIONS)

    if has_face:
        lm = face_results.multi_face_landmarks[0].landmark
        npt = p(lm, 1, w, h)
        fw = dist(p(lm, 454, w, h), p(lm, 234, w, h))
        if fw > 0:
            if not is_lying_down_solutions:
                tilt = calculate_tilt(p(lm, 33, w, h), p(lm, 263, w, h))
                if tilt > settings["TILT_THRESHOLD_DEG"]:
                    is_tilting = True
                    if state["tilt_start_time"] is None:
                        state["tilt_start_time"] = time.time()
                    if draw_progress_bar(frame, npt[0] - 50, npt[1] - 80, state["tilt_start_time"], settings["TILT_HOLD_DURATION"], (255, 0, 255), "TILT"):
                        posture_alert = True

            ne = ((dist(p(lm, 159, w, h), p(lm, 145, w, h)) + dist(p(lm, 386, w, h), p(lm, 374, w, h))) / 2) / fw
            nm = dist(p(lm, 13, w, h), p(lm, 14, w, h)) / fw
            nb = dist(p(lm, 107, w, h), p(lm, 336, w, h)) / fw

            if (ne < settings["EYE_SQUINT_THRESHOLD"] and nm > 0.10) or nm > 0.20 or ne < (settings["EYE_SQUINT_THRESHOLD"] - 0.005) or nb < settings["BROW_FURROW_THRESHOLD"]:
                is_pain = True
                if state["pain_start_time"] is None:
                    state["pain_start_time"] = time.time()
                if draw_progress_bar(frame, npt[0] + int(fw / 2) + 20, npt[1], state["pain_start_time"], settings["PAIN_HOLD_DURATION"], (0, 0, 255), "PAIN"):
                    face_alert = True

    if person_bboxes and has_pose:
        person_bbox, _ = person_bboxes[0]
        on_bed = state["bed_tracker"].is_person_on_bed(person_bbox)
        posture_f, _, _ = detect_posture_binary(person_bbox, pose_results.pose_landmarks.landmark, on_bed)

        is_lying_floor = posture_f == "Lying Down" and not on_bed
        if is_lying_floor:
            if state["fall_start_time_sustained"] is None:
                state["fall_start_time_sustained"] = time.time()
            if draw_progress_bar(frame, person_bbox[0], person_bbox[1] - 20, state["fall_start_time_sustained"], settings["FALL_HOLD_DURATION"], (0, 0, 255), "FALL RISK"):
                fall_alert = True
        else:
            state["fall_start_time_sustained"] = None

        cv2.rectangle(frame, (person_bbox[0], person_bbox[1]), (person_bbox[2], person_bbox[3]), (0, 255, 0), 1)
        cv2.putText(frame, f"FALL STATUS: {posture_f}", (w - 220, 30), cv2.FONT_HERSHEY_PLAIN, 1, (0, 255, 0), 1)
        cv2.putText(frame, f"LOCATION: {'ON BED' if on_bed else 'ON FLOOR'}", (w - 220, 50), cv2.FONT_HERSHEY_PLAIN, 1, (0, 255, 0), 1)
        if is_lying_floor:
            cv2.putText(frame, "LYING ON FLOOR!", (w - 220, 70), cv2.FONT_HERSHEY_PLAIN, 1, (0, 0, 255), 2)

    mode_label = "LYING DOWN" if (is_lying_down_solutions or posture_f == "Lying Down") else "SITTING/STANDING"

    if not is_slumping:
        state["slump_start_time"] = None
    if not is_tilting:
        state["tilt_start_time"] = None
    if not is_pain:
        state["pain_start_time"] = None

    hands_inside_box = is_clutching
    if hands_inside_box:
        if state["hand_enter_time"] is None:
            state["hand_enter_time"] = time.time()
        if draw_progress_bar(frame, 50, 100, state["hand_enter_time"], settings["HAND_HOLD_THRESHOLD"], (0, 255, 255), "HANDS"):
            body_alert = True
    else:
        state["hand_enter_time"] = None

    alerts = []
    if posture_alert:
        alerts.append("POSTURE")
    if body_alert:
        alerts.append("BODY")
    if face_alert:
        alerts.append("FACE")
    if fall_alert:
        alerts.append("FALL")

    if not alerts:
        cv2.putText(frame, "STATUS: NORMAL", (30, 50), cv2.FONT_HERSHEY_SIMPLEX, 0.8, (0, 255, 0), 2)

    return alerts, mode_label, True


def monitoring_loop():
    global voice_alert, system_state, machine_state
    global session_writer, session_writer2, event_writer, event_writer2
    global event_video_path, event_video_path_web2
    global grace_start_time, record_start_time, current_alert_types
    global latest_frame, latest_frame2, is_monitoring, exit_flag, current_event_id
    global camera_mode, camera1_choice, camera2_choice, resolved_camera1_idx, resolved_camera2_idx
    global monitoring_startup_ready, monitoring_startup_error

    cap1 = None
    cap2 = None
    monitoring_startup_ready = False
    monitoring_startup_error = None

    try:
        mapping = get_camera_mapping()
        default_camera_choice = settings.get("CAMERA_INDEX", 0)
        resolved_camera1_idx = resolve_camera_choice(camera1_choice, mapping, default_camera_choice)
        resolved_camera2_idx = None

        allow_primary_fallback = camera_mode == "single" and camera1_choice in (None, "")
        cap1, resolved_camera1_idx = open_camera(resolved_camera1_idx, "Primary camera", allow_primary_fallback)

        if camera_mode == "dual":
            resolved_camera2_idx = resolve_camera_choice(camera2_choice, mapping, default_camera_choice)
            if resolved_camera1_idx == resolved_camera2_idx:
                raise RuntimeError(f"Dual mode requires two different cameras, but both selections resolved to index {resolved_camera1_idx}.")
            cap2, resolved_camera2_idx = open_camera(resolved_camera2_idx, "Secondary camera")

        print(f"Opening cameras: Mode={camera_mode}, Cam1={resolved_camera1_idx}, Cam2={resolved_camera2_idx}")

        ret1, frame1 = cap1.read()
        if not ret1:
            raise RuntimeError("Primary camera opened but no frames could be read.")

        frame2 = None
        if cap2 is not None:
            ret2, frame2 = cap2.read()
            if not ret2:
                raise RuntimeError("Secondary camera opened but no frames could be read.")

        state1 = create_camera_state()
        state2 = create_camera_state() if cap2 is not None else None

        initialize_session_writers(frame1, frame2)
        monitoring_startup_ready = True

        last_voice_time = 0
        thumbs_up_start_time = None

        print("Loop started.")

        while not exit_flag and cap1.isOpened():
            initialize_session_writers(frame1, frame2)

            if session_writer is not None:
                session_writer.write(frame1)
            if session_writer2 is not None and frame2 is not None:
                session_writer2.write(frame2)

            frames_to_process = [(1, frame1, state1)]
            if frame2 is not None and state2 is not None:
                frames_to_process.append((2, frame2, state2))

            any_thumbs_up_this_tick = False
            pending_alerts = []
            observed_modes = []

            for cam_id, frame, state in frames_to_process:
                h, w, _ = frame.shape
                person_bboxes = prepare_fall_tracking(frame, state)
                rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)

                if machine_state == "LOCKED":
                    system_state["locked"] = True
                    system_state["message"] = " + ".join(current_alert_types)
                    overlay = frame.copy()
                    cv2.rectangle(overlay, (0, 0), (w, h), (0, 0, 150), -1)
                    frame = cv2.addWeighted(overlay, 0.4, frame, 0.6, 0)
                    cv2.putText(frame, f"ALERT: {system_state['message']}", (50, h // 2 - 50), cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 0, 255), 3)
                    cv2.putText(frame, "WAITING FOR CAREGIVER...", (50, h // 2 + 50), cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 255, 255), 2)

                elif machine_state == "RECORDING":
                    system_state["locked"] = True
                    system_state["message"] = "RECORDING EVENT..."
                    cv2.rectangle(frame, (0, 0), (w, h), (0, 0, 255), 4)
                    cv2.putText(frame, "RECORDING ALERT...", (50, 50), cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 0, 255), 3)
                    if cam_id == 1 and event_writer is not None:
                        event_writer.write(frame)
                    if cam_id == 2 and event_writer2 is not None:
                        event_writer2.write(frame)

                elif machine_state == "GRACE":
                    system_state["locked"] = True
                    system_state["message"] = "GRACE PERIOD - SHOW THUMBS UP"
                    time_left = max(0, settings["GRACE_PERIOD_DURATION"] - (time.time() - grace_start_time))
                    overlay = frame.copy()
                    cv2.rectangle(overlay, (0, 0), (w, h), (0, 255, 255), -1)
                    frame = cv2.addWeighted(overlay, 0.2, frame, 0.8, 0)
                    cv2.putText(frame, f"ALERT DETECTED! CANCEL: {int(time_left)}s", (30, 80), cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 255, 255), 3)

                    hand_results = hands.process(rgb)
                    thumbs_up_detected = False
                    if hand_results.multi_hand_landmarks:
                        for hand_lms in hand_results.multi_hand_landmarks:
                            mp.solutions.drawing_utils.draw_landmarks(frame, hand_lms, mp_hands.HAND_CONNECTIONS)
                            if is_thumbs_up(hand_lms):
                                thumbs_up_detected = True

                    if thumbs_up_detected:
                        any_thumbs_up_this_tick = True
                        if thumbs_up_start_time is None:
                            thumbs_up_start_time = time.time()
                        draw_progress_bar(frame, w // 2 - 50, h // 2, thumbs_up_start_time, 1.5, (0, 255, 0), "CANCELING")

                else:
                    alerts, mode_label, person_present = process_normal_frame(frame, rgb, state, person_bboxes)
                    if mode_label is not None:
                        observed_modes.append(mode_label)
                    if person_present:
                        if voice_alert:
                            last_voice_time = time.time()
                            voice_alert = False
                        if (time.time() - last_voice_time) < 5.0:
                            alerts.append("VOICE")
                    pending_alerts.extend(alerts)

                ret, buffer = cv2.imencode('.jpg', frame)
                if ret:
                    if cam_id == 1:
                        latest_frame = buffer.tobytes()
                    else:
                        latest_frame2 = buffer.tobytes()

            if machine_state == "NORMAL":
                if observed_modes:
                    system_state["mode"] = "LYING DOWN" if "LYING DOWN" in observed_modes else "SITTING/STANDING"

                if pending_alerts:
                    current_alert_types = list(dict.fromkeys(pending_alerts))
                    machine_state = "GRACE"
                    grace_start_time = time.time()
                    thumbs_up_start_time = None
                    system_state["locked"] = True
                    system_state["message"] = "GRACE PERIOD - SHOW THUMBS UP"
                    play_grace_prompt()
                else:
                    system_state["locked"] = False
                    system_state["message"] = ""

            elif machine_state == "GRACE":
                time_left = max(0, settings["GRACE_PERIOD_DURATION"] - (time.time() - grace_start_time))
                if any_thumbs_up_this_tick:
                    if thumbs_up_start_time is not None and (time.time() - thumbs_up_start_time) >= 1.5:
                        machine_state = "NORMAL"
                        system_state["locked"] = False
                        system_state["message"] = ""
                        current_alert_types = []
                        thumbs_up_start_time = None
                        for camera_state in [state1, state2]:
                            if camera_state is None:
                                continue
                            camera_state["slump_start_time"] = None
                            camera_state["tilt_start_time"] = None
                            camera_state["pain_start_time"] = None
                else:
                    thumbs_up_start_time = None

                if machine_state == "GRACE" and time_left <= 0:
                    machine_state = "RECORDING"
                    start_event_recording(frame1, frame2)

            elif machine_state == "RECORDING":
                if time.time() - record_start_time > 10.0:
                    finalize_event_recording()

            elif machine_state == "LOCKED":
                system_state["locked"] = True
                system_state["message"] = " + ".join(current_alert_types)

            ret1, next_frame1 = cap1.read()
            if not ret1:
                raise RuntimeError("Primary camera stream was lost.")
            frame1 = next_frame1

            if cap2 is not None:
                ret2, next_frame2 = cap2.read()
                if not ret2:
                    raise RuntimeError("Secondary camera stream was lost.")
                frame2 = next_frame2

            time.sleep(0.01)

    except Exception as exc:
        monitoring_startup_error = str(exc)
        print(f"Thread error: {exc}")
    finally:
        print("Releasing resources...")
        monitoring_startup_ready = False
        if session_writer is not None:
            session_writer.release()
            session_writer = None
        if session_writer2 is not None:
            session_writer2.release()
            session_writer2 = None
        if event_writer is not None:
            event_writer.release()
            event_writer = None
        if event_writer2 is not None:
            event_writer2.release()
            event_writer2 = None
        if cap1 is not None:
            cap1.release()
        if cap2 is not None:
            cap2.release()
        is_monitoring = False
        print("Cleanup done.")

# ===================
# FLASK
# ===================
def generate_frames(cam_id=1):
    global latest_frame, latest_frame2, is_monitoring, exit_flag
    while not exit_flag and is_monitoring:
        frame_bytes = latest_frame if cam_id == 1 else latest_frame2
        if frame_bytes:
            yield (b'--frame\r\n'
                   b'Content-Type: image/jpeg\r\n\r\n' + frame_bytes + b'\r\n')
        time.sleep(0.05)


@app.route('/status')
def status_route():
    return jsonify({
        "active": is_monitoring,
        "locked": system_state["locked"],
        "message": system_state["message"],
        "mode": system_state["mode"],
        "camera_mode": camera_mode,
        "machine_state": machine_state,
        "alert_types": current_alert_types,
        "current_event_id": current_event_id,
        "error": monitoring_startup_error
    })


@app.route('/resolve_alert', methods=['POST'])
def resolve_alert():
    global machine_state, current_event_id, system_state, current_alert_types
    data = request.json
    action = data.get('action')
    event_id = data.get('eventID')

    if not event_id:
        return jsonify({"status": "error", "message": "No event ID provided"}), 400

    try:
        requests.get(f"{WEBHOOK_BASE_URL}api_resolve_event.php?action={action}&id={event_id}", timeout=2)
    except Exception as exc:
        print("Error notifying PHP of resolution:", exc)

    machine_state = "NORMAL"
    system_state["locked"] = False
    system_state["message"] = ""
    current_alert_types = []
    current_event_id = None

    return jsonify({"status": "success"})


@app.route('/shutdown')
def shutdown_route():
    global exit_flag, current_elder_id, session_start_time, session_video_path_web, session_video_path_web2
    exit_flag = True
    print("Shutdown signal...")
    if current_elder_id and session_start_time and session_video_path_web:
        payload = {
            "elderID": current_elder_id,
            "startTime": session_start_time.strftime("%Y-%m-%d %H:%M:%S"),
            "endTime": datetime.now(MY_TZ).strftime("%Y-%m-%d %H:%M:%S"),
            "status": "Completed",
            "videoPath": session_video_path_web
        }
        if session_video_path_web2:
            payload["videoPath2"] = session_video_path_web2
        try:
            requests.post(f"{WEBHOOK_BASE_URL}api_log_session.php", json=payload, timeout=2)
        except Exception as exc:
            print("Session webhook error:", exc)

    def force_exit():
        time.sleep(2.0)
        os._exit(0)

    threading.Thread(target=force_exit, daemon=True).start()
    return "Shutting down..."


@app.route('/set_elder', methods=['POST'])
def set_elder():
    global current_elder_id, session_start_time
    global camera_mode, camera1_choice, camera2_choice
    global session_video_path_web, session_video_path_local, session_writer
    global session_video_path_web2, session_video_path_local2, session_writer2
    global event_writer, event_writer2, event_video_path, event_video_path_web2
    global machine_state, current_event_id, grace_start_time, record_start_time, current_alert_types
    global latest_frame, latest_frame2, is_monitoring, monitor_thread, exit_flag
    global monitoring_startup_ready, monitoring_startup_error

    data = request.json or {}
    new_id = data.get('elderID')
    if not new_id:
        return jsonify({"status": "error", "message": "Missing elderID."}), 400

    requested_mode = data.get('cameraMode', 'single')
    camera_mode = 'dual' if requested_mode == 'dual' else 'single'
    camera1_choice = data.get('camera1')
    camera2_choice = data.get('camera2')

    if camera_mode == 'dual' and (camera1_choice in (None, "") or camera2_choice in (None, "")):
        return jsonify({"status": "error", "message": "Dual mode requires both primary and secondary camera selections."}), 400

    if is_monitoring and current_elder_id == new_id:
        response = {"status": "already_running", "videoPath": session_video_path_web}
        if session_video_path_web2:
            response["videoPath2"] = session_video_path_web2
        return jsonify(response)

    current_elder_id = new_id
    timestamp = datetime.now(MY_TZ).strftime("%Y%m%d_%H%M%S")
    if camera_mode == "dual":
        session_video_path_local = f"../uploads/videos/session_{current_elder_id}_{timestamp}_cam1.webm"
        session_video_path_web = f"uploads/videos/session_{current_elder_id}_{timestamp}_cam1.webm"
        session_video_path_local2 = f"../uploads/videos/session_{current_elder_id}_{timestamp}_cam2.webm"
        session_video_path_web2 = f"uploads/videos/session_{current_elder_id}_{timestamp}_cam2.webm"
    else:
        session_video_path_local = f"../uploads/videos/session_{current_elder_id}_{timestamp}.webm"
        session_video_path_web = f"uploads/videos/session_{current_elder_id}_{timestamp}.webm"
        session_video_path_local2 = None
        session_video_path_web2 = None

    session_start_time = datetime.now(MY_TZ)
    session_writer = None
    session_writer2 = None
    event_writer = None
    event_writer2 = None
    event_video_path = None
    event_video_path_web2 = None
    machine_state = "NORMAL"
    current_event_id = None
    grace_start_time = None
    record_start_time = None
    current_alert_types = []
    latest_frame = None
    latest_frame2 = None
    system_state["locked"] = False
    system_state["message"] = ""
    system_state["mode"] = "NORMAL"
    monitoring_startup_ready = False
    monitoring_startup_error = None

    if not is_monitoring:
        exit_flag = False
        is_monitoring = True
        monitor_thread = threading.Thread(target=monitoring_loop, daemon=True)
        monitor_thread.start()

    startup_deadline = time.time() + 8.0
    while time.time() < startup_deadline:
        if monitoring_startup_error:
            is_monitoring = False
            current_elder_id = None
            session_start_time = None
            session_video_path_web = None
            session_video_path_local = None
            session_video_path_web2 = None
            session_video_path_local2 = None
            return jsonify({"status": "error", "message": monitoring_startup_error}), 500
        if monitoring_startup_ready:
            response = {"status": "success", "videoPath": session_video_path_web}
            if session_video_path_web2:
                response["videoPath2"] = session_video_path_web2
            return jsonify(response)
        if monitor_thread and not monitor_thread.is_alive() and not is_monitoring:
            break
        time.sleep(0.05)

    message = monitoring_startup_error or "Monitoring failed to initialize."
    current_elder_id = None
    session_start_time = None
    session_video_path_web = None
    session_video_path_local = None
    session_video_path_web2 = None
    session_video_path_local2 = None
    return jsonify({"status": "error", "message": message}), 500


@app.route('/video_feed')
@app.route('/video_feed1')
def video_feed1():
    return Response(generate_frames(1), mimetype='multipart/x-mixed-replace; boundary=frame')


@app.route('/video_feed2')
def video_feed2():
    return Response(generate_frames(2), mimetype='multipart/x-mixed-replace; boundary=frame')


if __name__ == '__main__':
    os.makedirs("../uploads/videos", exist_ok=True)
    app.run(host='0.0.0.0', port=5000, threaded=True)
