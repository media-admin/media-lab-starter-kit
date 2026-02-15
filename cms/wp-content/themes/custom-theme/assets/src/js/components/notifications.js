/**
 * Notifications / Toast Messages
 */

export default class Notifications {
  constructor() {
    this.container = null;
    this.init();
  }
  
  init() {
    // Create container if it doesn't exist
    this.createContainer();
    
    // Handle dismissible notifications
    this.handleDismissButtons();
    
    // Expose global function for programmatic notifications
    window.showNotification = (message, type = 'info', duration = 5000) => {
      this.show(message, type, duration);
    };
  }
  
  createContainer() {
    if (!document.querySelector('.notification-container')) {
      this.container = document.createElement('div');
      this.container.className = 'notification-container';
      document.body.appendChild(this.container);
    } else {
      this.container = document.querySelector('.notification-container');
    }
  }
  
  handleDismissButtons() {
    document.addEventListener('click', (e) => {
      if (e.target.classList.contains('notification__close')) {
        const notification = e.target.closest('.notification');
        this.dismiss(notification);
      }
    });
  }
  
  show(message, type = 'info', duration = 5000) {
    const icons = {
      success: 'dashicons-yes-alt',
      error: 'dashicons-dismiss',
      warning: 'dashicons-warning',
      info: 'dashicons-info',
    };
    
    const icon = icons[type] || icons.info;
    
    const notification = document.createElement('div');
    notification.className = `notification notification--${type}`;
    notification.setAttribute('role', 'alert');
    notification.innerHTML = `
      <span class="notification__icon dashicons ${icon}"></span>
      <div class="notification__content">
        <p>${message}</p>
      </div>
      <button class="notification__close" aria-label="Schließen">&times;</button>
    `;
    
    this.container.appendChild(notification);
    
    // Auto dismiss after duration
    if (duration > 0) {
      setTimeout(() => {
        this.dismiss(notification);
      }, duration);
    }
  }
  
  dismiss(notification) {
    notification.style.animation = 'fadeOut 0.3s ease-out';
    
    setTimeout(() => {
      if (notification.parentNode) {
        notification.parentNode.removeChild(notification);
      }
    }, 300);
  }
}

// Initialize
new Notifications();