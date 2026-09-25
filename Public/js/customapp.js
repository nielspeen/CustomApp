fetch('/customapp/content')
    .then(response => response.text())
    .then(data => {
        document.getElementById('customapp-content').innerHTML = data;
        document.dispatchEvent(new CustomEvent('customapp:loaded'));
    })
    .catch(error => {
        console.error('Error loading customapp content:', error);
    });

