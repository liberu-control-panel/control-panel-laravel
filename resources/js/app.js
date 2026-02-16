// Toast notification helper
window.showToast = function(message, type = 'success') {
    window.dispatchEvent(new CustomEvent('toast', {
        detail: { message, type }
    }));
};

// Form submission success handler
document.addEventListener('DOMContentLoaded', function() {
    // Listen for Livewire success events only if Livewire is present
    if (typeof Livewire !== 'undefined') {
        window.addEventListener('livewire:init', () => {
            Livewire.on('notify', (data) => {
                showToast(data.message || data, data.type || 'success');
            });
        });
    }
});