import encode from '@jsquash/webp/encode.js';

self.onmessage = async ({ data }) => {
    const { id, file, quality, lossless } = data;
    let bitmap;
    let canvas;

    try {
        bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
        canvas = new OffscreenCanvas(bitmap.width, bitmap.height);
        const context = canvas.getContext('2d', { willReadFrequently: true });
        if (!context) throw new Error('Impossibile leggere l’immagine.');
        context.drawImage(bitmap, 0, 0);
        const image = context.getImageData(0, 0, bitmap.width, bitmap.height);
        bitmap.close();
        bitmap = null;
        const bytes = await encode(image, { quality: Number(quality), lossless: lossless ? 1 : 0 });
        self.postMessage({ id, bytes }, [bytes]);
    } catch {
        self.postMessage({ id, error: 'Conversione non riuscita.' });
    } finally {
        bitmap?.close();
        if (canvas) {
            canvas.width = 0;
            canvas.height = 0;
        }
    }
};
