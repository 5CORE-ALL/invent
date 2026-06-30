const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('agent', {
    getSetup: () => ipcRenderer.invoke('getSetup'),
    saveSetup: (payload) => ipcRenderer.invoke('saveSetup', payload),
    login: (payload) => ipcRenderer.invoke('login', payload),
    signOut: () => ipcRenderer.invoke('signOut'),
    getState: () => ipcRenderer.invoke('getState'),
    clockIn: (payload) => ipcRenderer.invoke('clockIn', payload),
    clockOut: () => ipcRenderer.invoke('clockOut'),
    pause: () => ipcRenderer.invoke('pause'),
    resume: () => ipcRenderer.invoke('resume'),
    minimizeToTray: () => ipcRenderer.invoke('minimizeToTray'),
    openPortal: () => ipcRenderer.invoke('openPortal'),
    onStats: (cb) => {
        ipcRenderer.on('stats-update', (_e, data) => cb(data));
    },
});
