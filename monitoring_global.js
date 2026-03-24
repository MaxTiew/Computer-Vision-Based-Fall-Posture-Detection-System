
// monitoring_global.js

(function() {
    let pollInterval;

    // Inject Shared Styles immediately so they are available on all pages
    const injectStyles = () => {
        if (document.getElementById('global-monitoring-styles')) return;
        const style = document.createElement('style');
        style.id = 'global-monitoring-styles';
        style.innerHTML = `
            #global-monitoring-widget {
                position: fixed;
                bottom: 20px;
                right: 20px;
                width: 240px;
                background: white;
                border-radius: 12px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                z-index: 9999;
                overflow: hidden;
                font-family: 'Segoe UI', sans-serif;
                border: 2px solid #4a90e2;
            }
            .widget-header {
                background: #4a90e2;
                color: white;
                padding: 8px 12px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-size: 12px;
                font-weight: bold;
            }
            .widget-header button { background: transparent; border: none; color: white; cursor: pointer; }
            .widget-body { position: relative; aspect-ratio: 16/9; background: #000; }
            #global-stream { width: 100%; height: 100%; object-fit: cover; }
            .widget-status-overlay {
                position: absolute;
                top: 8px;
                left: 8px;
                background: rgba(39, 174, 96, 0.8);
                color: white;
                padding: 2px 6px;
                border-radius: 4px;
                font-size: 10px;
                font-weight: bold;
            }
            .global-alert-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                background: transparent;
                border: 12px solid #e74c3c;
                box-sizing: border-box;
                z-index: 999999; /* Ensure it stays above everything */
                pointer-events: none;
                display: none;
                animation: pulse-border 1s infinite alternate;
            }
            .alert-box {
                position: fixed;
                top: 40px;
                left: 50%;
                transform: translateX(-50%);
                background: #e74c3c;
                color: white;
                padding: 18px 35px;
                border-radius: 50px;
                font-weight: bold;
                font-size: 16px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.4);
                display: flex;
                align-items: center;
                gap: 15px;
                z-index: 1000000;
                pointer-events: auto; /* Allow clicking the button */
                white-space: nowrap;
            }
            @keyframes pulse-border {
                from { border-width: 8px; }
                to { border-width: 18px; }
            }
        `;
        document.head.appendChild(style);
    };

    async function checkMonitoringStatus() {
        try {
            const response = await fetch('http://localhost:5000/status');
            if (response.ok) {
                const data = await response.json();
                updateUI(true, data);
            } else {
                updateUI(false);
            }
        } catch (error) {
            updateUI(false);
        }
    }

    function updateUI(active, data = null) {
        const isMonitoringPage = window.location.pathname.includes('active_monitoring.php');
        
        // 1. Sidebar Link Update
        const navLinks = document.querySelectorAll('.nav-links a');
        navLinks.forEach(link => {
            if (link.href.includes('monitoring.php')) {
                let badge = link.querySelector('.monitoring-badge');
                if (active) {
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'monitoring-badge';
                        badge.innerHTML = '<i class="fa-solid fa-circle" style="color: #27ae60; font-size: 8px; margin-left: 10px;"></i>';
                        link.appendChild(badge);
                    }
                } else if (badge) {
                    badge.remove();
                }
            }
        });

        // 2. Floating Widget (only if not on active_monitoring.php)
        if (active && !isMonitoringPage) {
            showFloatingWidget(data);
        } else {
            hideFloatingWidget();
        }

        // 3. Global Alert Overlay (RECORDING or LOCKED)
        const isConfirmedAlert = data && (data.machine_state === "RECORDING" || data.machine_state === "LOCKED");
        if (active && isConfirmedAlert) {
            const alertMsg = (data.alert_types && data.alert_types.length > 0) 
                ? data.alert_types.join(' + ') 
                : (data.message || 'CRITICAL');
            
            showGlobalAlert(alertMsg, isMonitoringPage);
        } else {
            hideGlobalAlert();
        }
    }

    function showFloatingWidget(data) {
        let widget = document.getElementById('global-monitoring-widget');
        if (!widget) {
            const activeMonitoringUrl = window.appendGoodLifeAuthUrl ? window.appendGoodLifeAuthUrl('active_monitoring.php') : 'active_monitoring.php';
            widget = document.createElement('div');
            widget.id = 'global-monitoring-widget';
            widget.innerHTML = `
                <div class="widget-header">
                    <span><i class="fa-solid fa-video"></i> Live Monitor</span>
                    <button onclick="window.location.href='${activeMonitoringUrl}'"><i class="fa-solid fa-expand"></i></button>
                </div>
                <div class="widget-body">
                    <img id="global-stream" src="http://localhost:5000/video_feed" alt="Stream">
                    <div id="widget-status" class="widget-status-overlay">Active</div>
                </div>
            `;
            document.body.appendChild(widget);
        }

        const statusOverlay = document.getElementById('widget-status');
        if (data && (data.machine_state === "RECORDING" || data.machine_state === "LOCKED")) {
            statusOverlay.style.background = 'rgba(231, 76, 60, 0.8)';
            statusOverlay.innerText = 'ALERT';
        } else {
            statusOverlay.style.background = 'rgba(39, 174, 96, 0.8)';
            statusOverlay.innerText = 'Active';
        }
    }

    function hideFloatingWidget() {
        const widget = document.getElementById('global-monitoring-widget');
        if (widget) widget.remove();
    }

    function showGlobalAlert(message, isMonitoringPage) {
        let alertOverlay = document.getElementById('global-alert-overlay');
        if (!alertOverlay) {
            alertOverlay = document.createElement('div');
            alertOverlay.id = 'global-alert-overlay';
            alertOverlay.className = 'global-alert-overlay';
            document.body.appendChild(alertOverlay);
        }

        const activeMonitoringUrl = window.appendGoodLifeAuthUrl ? window.appendGoodLifeAuthUrl('active_monitoring.php') : 'active_monitoring.php';
        alertOverlay.innerHTML = `
            <div class="alert-box">
                <i class="fa-solid fa-circle-exclamation fa-beat"></i>
                CRITICAL ALERT: ${message}
                ${!isMonitoringPage ? `<button onclick="window.location.href='${activeMonitoringUrl}'" style="background:white; color:#e74c3c; border:none; padding:5px 15px; border-radius:20px; font-weight:bold; cursor:pointer; margin-left:10px;">VIEW</button>` : ''}
            </div>
        `;
        alertOverlay.style.display = 'block';
    }

    function hideGlobalAlert() {
        const alertOverlay = document.getElementById('global-alert-overlay');
        if (alertOverlay) alertOverlay.style.display = 'none';
    }

    // Initialize
    injectStyles();
    pollInterval = setInterval(checkMonitoringStatus, 2000);
    checkMonitoringStatus();

})();
