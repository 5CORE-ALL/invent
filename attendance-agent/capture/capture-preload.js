const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('capture', {
    onStart: (cb) => ipcRenderer.on('capture-start', (_e, opts) => cb(opts)),
    onStop: (cb) => ipcRenderer.on('capture-stop', () => cb()),
    sendChunk: (payload) => ipcRenderer.send('capture-chunk', payload),
    sendStatus: (payload) => ipcRenderer.send('capture-status', payload),
});
