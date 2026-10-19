import { pipeline } from '@huggingface/transformers';

let segmenter;

self.onmessage = async ({ data }) => {
    const { file } = data;
    try {
        if (!segmenter) {
            self.postMessage({ type: 'status', message: 'Preparazione del modello…' });
            segmenter = await pipeline('background-removal', 'onnx-community/MVANet-ONNX', {
                dtype: 'q8',
                progress_callback: event => {
                    if (event.status === 'progress' && event.progress != null) {
                        self.postMessage({ type: 'status', message: `Caricamento modello: ${Math.round(event.progress)}%` });
                    }
                },
            });
        }
        self.postMessage({ type: 'status', message: 'Rimozione dello sfondo…' });
        const image = await segmenter(file);
        let transparentPixels = 0;
        for (let index = 3; index < image.data.length; index += 4) {
            if (image.data[index] < 250) transparentPixels++;
        }
        if (image.channels !== 4 || transparentPixels < image.width * image.height * 0.005) {
            self.postMessage({ type: 'error', message: 'Non siamo riusciti a distinguere lo sfondo in questa immagine.' });
            return;
        }
        const bytes = await (await image.toBlob()).arrayBuffer();
        self.postMessage({ type: 'done', bytes }, [bytes]);
    } catch (error) {
        self.postMessage({ type: 'error', message: 'Elaborazione non riuscita. Verifica la connessione e riprova.' });
    }
};
