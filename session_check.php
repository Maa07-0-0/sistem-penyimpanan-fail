<?php
// Session timeout check - 5 minutes (300 seconds)
$timeout_duration = 300;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if session has timed out
if (isset($_SESSION['last_activity'])) {
    $time_elapsed = time() - $_SESSION['last_activity'];
    
    if ($time_elapsed > $timeout_duration) {
        // Session has expired
        session_unset();
        session_destroy();
        
        // Return JSON response if it's an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'timeout', 'message' => 'Sesi telah tamat tempoh. Sila log masuk semula.']);
            exit;
        }
        
        // Redirect to login with timeout message
        header('Location: login.php?timeout=1');
        exit;
    }
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Calculate remaining time for JavaScript
$remaining_time = $timeout_duration - (time() - $_SESSION['last_activity']);
?>

<script>
// Auto logout functionality
let sessionTimeout = <?= $remaining_time ?> * 1000; // Convert to milliseconds
let warningTime = 60 * 1000; // Show warning 1 minute before timeout
let warningShown = false;
let countdownInterval;
let timeoutTimer;

// Function to reset the session timer
function resetSessionTimer() {
    clearTimeout(timeoutTimer);
    clearInterval(countdownInterval);
    warningShown = false;
    
    // Hide warning modal if shown
    const warningModal = document.getElementById('sessionWarningModal');
    if (warningModal) {
        const modal = bootstrap.Modal.getInstance(warningModal);
        if (modal) {
            modal.hide();
        }
    }
    
    // Reset timers
    sessionTimeout = <?= $timeout_duration ?> * 1000;
    startSessionTimer();
    
    // Update server session
    fetch('update_session.php', { method: 'POST' })
        .catch(err => console.log('Session update failed:', err));
}

// Function to start session timer
function startSessionTimer() {
    // Warning timer
    setTimeout(() => {
        if (!warningShown) {
            showSessionWarning();
        }
    }, sessionTimeout - warningTime);
    
    // Logout timer
    timeoutTimer = setTimeout(() => {
        autoLogout();
    }, sessionTimeout);
}

// Function to show session warning
function showSessionWarning() {
    warningShown = true;
    
    // Create warning modal if it doesn't exist
    if (!document.getElementById('sessionWarningModal')) {
        createWarningModal();
    }
    
    const warningModal = new bootstrap.Modal(document.getElementById('sessionWarningModal'));
    warningModal.show();
    
    // Start countdown
    let countdown = 60;
    const countdownElement = document.getElementById('countdownTimer');
    
    countdownInterval = setInterval(() => {
        countdown--;
        countdownElement.textContent = countdown;
        
        if (countdown <= 0) {
            clearInterval(countdownInterval);
            autoLogout();
        }
    }, 1000);
}

// Function to create warning modal
function createWarningModal() {
    const modalHTML = `
        <div class="modal fade" id="sessionWarningModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title">
                            <i class="fas fa-exclamation-triangle me-2"></i>Amaran Sesi
                        </h5>
                    </div>
                    <div class="modal-body text-center">
                        <i class="fas fa-clock fa-3x text-warning mb-3"></i>
                        <h5>Sesi anda akan tamat tempoh dalam:</h5>
                        <h2 class="text-danger mb-3">
                            <span id="countdownTimer">60</span> saat
                        </h2>
                        <p class="text-muted">Klik "Kekal Log Masuk" untuk meneruskan atau anda akan dilog keluar secara automatik.</p>
                    </div>
                    <div class="modal-footer justify-content-center">
                        <button type="button" class="btn btn-primary" onclick="extendSession()">
                            <i class="fas fa-refresh me-1"></i>Kekal Log Masuk
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="autoLogout()">
                            <i class="fas fa-sign-out-alt me-1"></i>Log Keluar Sekarang
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

// Function to extend session
function extendSession() {
    resetSessionTimer();
}

// Function to auto logout
function autoLogout() {
    // Show loading message
    const loadingHTML = `
        <div class="modal fade show" id="logoutModal" tabindex="-1" style="display: block;" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-body text-center p-4">
                        <div class="spinner-border text-primary mb-3" role="status"></div>
                        <h5>Sedang log keluar...</h5>
                        <p class="text-muted mb-0">Sesi telah tamat tempoh. Anda akan diarahkan ke halaman log masuk.</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', loadingHTML);
    
    // Redirect to logout
    setTimeout(() => {
        window.location.href = 'logout.php?timeout=1';
    }, 2000);
}

// Event listeners to reset timer on user activity
const resetEvents = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];

resetEvents.forEach(event => {
    document.addEventListener(event, () => {
        if (!warningShown) {
            resetSessionTimer();
        }
    }, true);
});

// Start the session timer when page loads
document.addEventListener('DOMContentLoaded', function() {
    startSessionTimer();
});

// Heartbeat to keep session alive (every 2 minutes)
setInterval(() => {
    if (!warningShown) {
        fetch('heartbeat.php', { method: 'POST' })
            .catch(err => console.log('Heartbeat failed:', err));
    }
}, 120000); // 2 minutes
</script>