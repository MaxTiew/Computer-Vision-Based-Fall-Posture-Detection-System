# GoodLife Vision – Smart Elderly Monitoring System

## Overview
GoodLife Vision is a web-based elderly monitoring system that combines a PHP caregiver portal with a Python computer-vision engine. It helps caregivers manage elderly/OKU profiles, start live monitoring sessions, detect risky situations, record sessions and alert clips, and review monitoring history.

The system uses:
- **PHP + MySQL** for the web app, authentication, profile management, alert history, and settings.
- **Python + Flask + OpenCV + MediaPipe + YOLO** for real-time monitoring, posture analysis, fall-related detection, voice-trigger detection, video streaming, and event recording.

---

## Main Functions in the System

### 1) Caregiver account system
- Caregiver registration
- Caregiver login
- Logout
- “Remember me” session support
- Forgot password flow with email reset link
- Reset password page

### 2) Dashboard
After login, the caregiver can access the main dashboard to:
- View total elderly/OKU profiles
- Jump to start monitoring
- Open profile management
- Open alert/session history
- Open settings
- See whether there are pending alerts

### 3) Elder/OKU profile management
The system lets the caregiver:
- Add a new elderly/OKU profile
- Upload a profile photo
- Store age and medical notes
- Store a primary emergency contact
- Edit an existing profile
- Delete a profile

### 4) Live monitoring setup
Before monitoring starts, the caregiver can:
- Select the elderly/OKU profile to monitor
- Choose **single camera mode** or **dual camera mode**
- Select primary and secondary camera sources

### 5) Real-time monitoring
During monitoring, the system can:
- Show live camera stream from the Python Flask server
- Run continuous AI-based analysis on the video
- Display session timer and session details
- Show system state such as normal, grace period, recording, and locked alert state
- Stop monitoring and save the session

### 6) Detection and alert functions
From the code inspection, the detection engine includes:
- **Posture alert**
  - slump/slouch detection
  - head tilt detection
- **Face/pain-related alert**
  - squint / mouth / brow cues used as pain-like indicators
- **Body alert**
  - hands near chest/stomach for sustained duration
- **Fall-related alert**
  - lying on floor for sustained duration
- **Voice keyword alert**
  - listens for words such as: help, ah, ahh, ouch, ow, pain, emergency, stop, doctor, hurt
- **Thumbs-up cancel gesture**
  - during the grace period, the elderly user can show thumbs up to cancel the alert

### 7) Grace period and alert workflow
The alert flow works like this:
1. Suspicious condition is detected
2. System enters **grace period**
3. A voice prompt asks whether the user is okay
4. If thumbs up is detected in time, the alert is canceled
5. If not canceled, the system starts **event recording**
6. The event is logged and becomes a caregiver alert
7. Caregiver can later acknowledge or dismiss the event

### 8) Session and event recording
The system records:
- **Full monitoring session video**
- **10-second event/alert clip** when a real alert is confirmed
- In dual-camera mode, the system can store separate cam1/cam2 videos for both session and alert events

### 9) Alert and session history
The **Alerts** page lets the caregiver:
- View all alert records
- View all full monitoring session records
- Filter/search records
- Watch alert videos
- Watch full session videos
- Delete individual alerts/sessions
- Clear all alerts or all sessions
- Mark alert as acknowledged or dismissed

### 10) Email notification support
The project includes email notification logic using PHPMailer. When an alert is confirmed, the system is designed to:
- Fetch caregiver email and alert details
- Send an alert email
- Include alert information and a direct system link
- Optionally attach or reference the alert video

### 11) AI settings page
The **Settings** page allows caregivers/admins to tune the monitoring engine, including:
- Slump threshold
- Tilt threshold
- Eye squint threshold
- Brow furrow threshold
- Hand hold threshold
- Pain hold duration
- Fall hold duration
- Slump hold duration
- Tilt hold duration
- Grace period duration
- Voice keywords
- Grace-period voice prompt text
- Custom grace-period audio upload

### 12) Global monitoring widget
The project also contains a shared JavaScript widget that can:
- Poll the Python monitoring server status
- Show a small floating live-monitor widget on non-monitoring pages
- Show a global critical-alert overlay when a confirmed alert is active

---

## System Pages and How to Access Them

Assuming the project is placed inside XAMPP htdocs as:

`C:\xampp\htdocs\goodlife`

Open in browser:

`http://localhost/goodlife/`

Main pages:
- `login.php` → login page
- `register.php` → create caregiver account
- `forgot_password.php` → request password reset
- `reset_password.php` → set new password from reset link
- `dashboard.php` → main dashboard after login
- `profiles.php` → manage elderly/OKU profiles
- `monitoring.php` → choose profile and camera mode, then start monitoring
- `active_monitoring.php` → live monitoring screen
- `alerts.php` → alert history and session recordings
- `settings.php` → AI thresholds, keywords, and grace prompt settings
- `logout.php` → logout

---

## How to Run the System

### 1) Software needed
Install these first:
- **XAMPP** (Apache + MySQL + PHP)
- **Python 3.11** recommended
- Windows environment is assumed by the current project setup

### 2) Put the project in htdocs
Place the project folder here:

`C:\xampp\htdocs\goodlife`

### 3) Start Apache and MySQL
Open XAMPP Control Panel and start:
- Apache
- MySQL

### 4) Create the database
Open phpMyAdmin and create a database named:

`goodlife_vision`

Important:
- The zip I inspected does **not include an SQL schema/export file**.
- So before other people can run the system, you should also upload or provide your database SQL file separately.
- The code expects tables such as caregiver, elderprofile, emergencycontact, eventlog, and monitoringsession.

### 5) Check database connection
The current database file is:
- `db_connect.php`

Current default values:
- host: `localhost`
- username: `root`
- password: empty
- database: `goodlife_vision`

If your MySQL setup is different, update `db_connect.php`.

### 6) Install PHP dependency
This project uses PHPMailer.

If the `vendor` folder is missing on another machine, run:

```bash
composer install
```

### 7) Install Python dependencies
The Python monitoring engine uses libraries such as:
- opencv-python
- mediapipe
- numpy
- sounddevice
- vosk
- requests
- torch
- ultralytics
- flask
- flask-cors
- pygame
- pyttsx3
- pygrabber

You can install them with pip as needed.

Example:

```bash
pip install opencv-python mediapipe numpy sounddevice vosk requests torch ultralytics flask flask-cors pygame pyttsx3 pygrabber
```

### 8) Make sure Python model files stay in place
Do not move these files out of `python file/`:
- `smart_detection.py`
- `face_landmarker.task`
- `hand_landmarker.task`
- `pose_landmarker_lite.task`
- `yolov8n.pt`
- `settings.json`

### 9) Start using the web system
Open:

`http://localhost/goodlife/login.php`

Then:
1. Register a caregiver account
2. Login
3. Add at least one elderly/OKU profile in **Profiles**
4. Go to **Monitoring**
5. Choose profile and camera mode
6. Start monitoring

### 10) Python monitoring server behavior
The project is designed so that the PHP page calls `start_python.php`, which launches:
- `python file/smart_detection.py`

That Python script starts a Flask server at:
- `http://localhost:5000`

The web app then uses endpoints like:
- `/status`
- `/set_elder`
- `/video_feed`
- `/video_feed1`
- `/video_feed2`
- `/resolve_alert`
- `/shutdown`

If the camera or stream does not work, first check:
- Apache is running
- MySQL is running
- Python 3.11 is installed and callable from terminal using `py -3.11`
- Port `5000` is not blocked
- Your webcam is not being used by another app

---

## How to Use the System

### A) Register and login
- Open `register.php` to create a caregiver account
- Then login from `login.php`

### B) Add elderly/OKU profile
Go to **Profiles**:
- Click **Add New Profile**
- Fill in name, age, medical notes, and emergency contact
- Upload a profile image if needed
- Save

### C) Configure AI settings
Go to **Settings**:
- Adjust detection thresholds and hold durations
- Change grace period keyword list
- Upload a custom grace-period audio file if needed
- Save settings

### D) Start monitoring
Go to **Monitoring**:
- Select the elderly/OKU profile
- Choose single or dual camera mode
- Choose camera source(s)
- Click **Start Monitoring**

### E) During live monitoring
On the live monitoring page, the system will:
- Start camera stream
- Analyze posture / fall / pain / voice cues
- Display current status
- Enter grace period if suspicious activity is detected
- Record alert clip if the alert is not canceled

### F) Resolve alerts
When an alert becomes confirmed:
- Caregiver can go to the monitoring page or alerts page
- The alert can be acknowledged or dismissed
- Alert and session videos can be reviewed later

### G) Review history
Go to **Alerts**:
- View alert records
- View full session records
- Watch saved videos
- Delete records if needed

---

## Important Notes for GitHub Users

Before sharing this project publicly, make sure to check these items:

### 1) Database export
Upload a `.sql` file for the database structure and sample data, otherwise other users cannot run the project easily.

### 2) Environment-specific paths
The file `start_python.php` currently uses a hardcoded Windows path:

`C:\xampp\htdocs\goodlife\python file\smart_detection.py`

If someone stores the project in a different folder, they must update that path.

### 3) Localhost-only assumptions
Some parts still assume local hosting:
- web app at `http://localhost/goodlife/`
- Python Flask server at `http://localhost:5000`

So remote deployment will need changes.

### 4) Email credentials
The email settings in the code currently look like placeholders (`xxx@gmail.com`, `xxxx xxxx xxxx xxxx`). That is good for public GitHub, but users must replace them with their own SMTP credentials before email alerts can work.

### 5) Security note
From the current PHP code, caregiver passwords appear to be stored and checked in a simple way rather than using strong password hashing. For academic/demo use it may still run, but for real deployment this should be upgraded.

---


## Short Project Summary
GoodLife Vision is an AI-based elderly monitoring system that helps caregivers supervise elderly/OKU users through live camera monitoring, posture and fall-related analysis, grace-period alert confirmation, session/event recording, alert history review, email notification, and adjustable detection settings.
