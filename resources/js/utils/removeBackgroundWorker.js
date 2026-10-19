let worker = null;
let idleTimer = null;

export function getRemoveBackgroundWorker() {
    if (idleTimer) clearTimeout(idleTimer);
    idleTimer = null;
    if (!worker) {
        worker = new Worker(new URL('../workers/remove-background.worker.js', import.meta.url), { type: 'module' });
    }
    return worker;
}

export function releaseRemoveBackgroundWorker() {
    if (!worker) return;
    const current = worker;
    idleTimer = setTimeout(() => {
        if (worker === current) {
            worker.terminate();
            worker = null;
        }
        idleTimer = null;
    }, 10 * 60 * 1000);
}

export function resetRemoveBackgroundWorker() {
    if (idleTimer) clearTimeout(idleTimer);
    idleTimer = null;
    worker?.terminate();
    worker = null;
}
