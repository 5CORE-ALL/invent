const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('idlePrompt', {
    respond: (choice) => ipcRenderer.send('idle-prompt-response', choice),
    getConfig: () => ipcRenderer.invoke('idle-prompt-config'),
    onStats: (cb) => {
        const handler = (_e, data) => cb(data);
        ipcRenderer.on('idle-prompt-stats', handler);
        return () => ipcRenderer.removeListener('idle-prompt-stats', handler);
    },
    onPhase: (cb) => {
        const handler = (_e, data) => cb(data);
        ipcRenderer.on('idle-prompt-phase', handler);
        return () => ipcRenderer.removeListener('idle-prompt-phase', handler);
    },
    resize: (width, height) => ipcRenderer.send('idle-prompt-resize', { width, height }),
});
