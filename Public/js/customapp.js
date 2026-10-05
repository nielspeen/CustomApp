// The Custom App section of the conversation's customer inspector, when the
// mailbox has a callback URL (partials/sidebar). On each page, also one opened
// with wire:navigate, and when a conversation opens in place.
var customappLoad = function () {
    var container = document.getElementById('customapp-content');
    if (!container) {
        return;
    }
    fetch('/customapp/content')
        .then(response => response.text())
        .then(data => {
            container.innerHTML = data;
            document.dispatchEvent(new CustomEvent('customapp:loaded'));
        })
        .catch(error => {
            console.error('Error loading customapp content:', error);
        });
};
document.addEventListener('livewire:navigated', customappLoad);
document.addEventListener('tallport:conversation-opened', customappLoad);
