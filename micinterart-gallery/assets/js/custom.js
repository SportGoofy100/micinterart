document.addEventListener('wpcf7mailsent', function(event) {
    // Prüfe, ob in der Antwort eine PayPal-URL mitgeschickt wurde
    if (event.detail.apiResponse && event.detail.apiResponse.paypal_redirect_url) {
        const redirectUrl = event.detail.apiResponse.paypal_redirect_url;
        
        // Kleine Verzögerung (500ms), damit der Nutzer die Erfolgsmeldung kurz sieht
        setTimeout(function() {
            window.location.href = redirectUrl;
        }, 800);
    }
}, false);