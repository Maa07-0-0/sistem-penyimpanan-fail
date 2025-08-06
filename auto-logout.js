// Auto Logout System - 5 minutes inactivity
class AutoLogout {
    constructor() {
        this.timeoutDuration = 5 * 60 * 1000; // 5 minutes
        this.warningDuration = 1 * 60 * 1000; // Warning 1 minute before
        this.timeoutId = null;
        this.warningTimeoutId = null;
        this.countdownInterval = null;
        this.remainingTime = 0;
        
        this.init();
    }
    
    init() {
        this.createWarningModal();
        this.bindEvents();
        this.resetTimer();
    }
    
    createWarningModal() {
        const modalHTML = `
            <div class="modal fade" id="autoLogoutModal" tabindex="-1" data-bs-backdrop="static">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title">
                                <i class="fas fa-exclamation-triangle me-2"></i>Amaran Sesi
                            </h5>
                        </div>
                        <div class="modal-body text-center">
                            <i class="fas fa-clock fa-3x text-warning mb-3"></i>
                            <h5>Sesi akan tamat dalam:</h5>
                            <div id="countdown" class="display-4 text-danger fw-bold">60</div>
                            <p class="text-muted">saat</p>
                            <p>Klik "Kekal Log Masuk" untuk meneruskan.</p>
                        </div>
                        <div class="modal-footer justify-content-center">
                            <button type="button" class="btn btn-success" onclick="autoLogout.extendSession()">
                                <i class="fas fa-refresh me-2"></i>Kekal Log Masuk
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="autoLogout.logoutNow()">
                                <i class="fas fa-sign-out-alt me-2"></i>Log Keluar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }
    
    bindEvents() {
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        events.forEach(event => {
            document.addEventListener(event, () => this.resetTimer(), true);
        });
    }
    
    resetTimer() {
        clearTimeout(this.timeoutId);
        clearTimeout(this.warningTimeoutId);
        clearInterval(this.countdownInterval);
        
        // Hide modal if showing
        const modal = document.getElementById('autoLogoutModal');
        if (modal && modal.classList.contains('show')) {
            bootstrap.Modal.getInstance(modal).hide();
        }
        
        // Set warning timer (4 minutes)
        this.warningTimeoutId = setTimeout(() => this.showWarning(), this.timeoutDuration - this.warningDuration);
        
        // Set logout timer (5 minutes)
        this.timeoutId = setTimeout(() => this.logout(), this.timeoutDuration);
    }
    
    showWarning() {
        this.remainingTime = 60;
        const modal = new bootstrap.Modal(document.getElementById('autoLogoutModal'));
        modal.show();
        
        this.countdownInterval = setInterval(() => {
            this.remainingTime--;
            document.getElementById('countdown').textContent = this.remainingTime;
            
            if (this.remainingTime <= 0) {
                clearInterval(this.countdownInterval);
                this.logout();
            }
        }, 1000);
    }
    
    extendSession() {
        const modal = bootstrap.Modal.getInstance(document.getElementById('autoLogoutModal'));
        modal.hide();
        clearInterval(this.countdownInterval);
        this.resetTimer();
        this.showNotification('Sesi dilanjutkan untuk 5 minit lagi.', 'success');
    }
    
    logoutNow() {
        this.logout();
    }
    
    logout() {
        const modal = document.getElementById('autoLogoutModal');
        if (modal && modal.classList.contains('show')) {
            bootstrap.Modal.getInstance(modal).hide();
        }
        
        clearTimeout(this.timeoutId);
        clearTimeout(this.warningTimeoutId);
        clearInterval(this.countdownInterval);
        
        this.showNotification('Sesi tamat. Menglog keluar...', 'warning');
        
        setTimeout(() => {
            window.location.href = 'logout.php?reason=timeout';
        }, 2000);
    }
    
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('.sidebar')) {
        window.autoLogout = new AutoLogout();
    }
});