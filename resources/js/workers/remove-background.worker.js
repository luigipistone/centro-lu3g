import { pipeline } from '@huggingface/transformers';

let segmenter;

self.onmessage = async ({ data }) => {
    const { file } = data;
    try {
        if (!segmenter) {
            self.postMessage({ type: 'status', message: 'Preparazione del modello…' });
            segmenter = await pipeline('background-removal', 'onnx-community/ormbg-ONNX', {
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
        const bytes = await (await image.toBlob()).arrayBuffer();
        self.postMessage({ type: 'done', bytes }, [bytes]);
    } catch (error) {
        self.postMessage({ type: 'error', message: error?.message || 'Elaborazione non riuscita.' });
    }
};
