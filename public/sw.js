self.addEventListener('push', function (e) {
    if (!(self.Notification && self.Notification.permission === 'granted')) {
        return;
    }

    if (e.data) {
        let msg = e.data.json();
        e.waitUntil(self.registration.showNotification(msg.title, {
            body: msg.body,
            icon: msg.icon || '/icon.png',
            badge: msg.badge || '/badge.png',
            data: msg.data || {},
            actions: msg.actions || []
        }));
    }
});

self.addEventListener('notificationclick', function (e) {
    e.notification.close();
    
    // Si hay una URL en los datos, abre esa URL
    if (e.notification.data && e.notification.data.url) {
        e.waitUntil(
            clients.matchAll({ type: 'window' }).then(windowClients => {
                // Comprobar si ya hay una ventana abierta con esa URL y enfocarla
                for (var i = 0; i < windowClients.length; i++) {
                    var client = windowClients[i];
                    if (client.url === e.notification.data.url && 'focus' in client) {
                        return client.focus();
                    }
                }
                // Si no, abrir una nueva
                if (clients.openWindow) {
                    return clients.openWindow(e.notification.data.url);
                }
            })
        );
    }
});
