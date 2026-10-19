<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class BackgroundRemovalController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Centro/RemoveBackground', [
            'configured' => filled(config('services.leonardo.api_key')),
        ]);
    }

    public function process(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:7168'],
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $image = $request->file('image');

        $key = config('services.leonardo.api_key');
        if (! filled($key)) {
            return response()->json(['message' => 'Lo strumento non e ancora configurato.'], 503);
        }

        try {
            $result = Http::withToken($key)
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(120)
                ->post('https://cloud.leonardo.ai/api/rest/v2/generationssync', [
                    'model' => 'remove-bg',
                    'ephemeral' => true,
                    'base64' => true,
                    'parameters' => [
                        'size' => 'auto',
                        'format' => 'png',
                        'guidances' => [
                            'image_reference' => [[
                                'image' => [
                                    'type' => 'BASE64',
                                    'data' => base64_encode(file_get_contents($image->getRealPath())),
                                ],
                            ]],
                        ],
                    ],
                ]);
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json(['message' => 'Il servizio non risponde. Riprova tra poco.'], 502);
        }

        if ($result->status() === 429) {
            return response()->json(['message' => 'Troppe richieste. Riprova tra poco.'], 429);
        }
        if (! $result->successful() || ! empty($result->json('errors'))) {
            return response()->json(['message' => 'Non e stato possibile rimuovere lo sfondo. Riprova con un\'altra immagine.'], 502);
        }

        $data = $result->json('results.0.dataB64');
        $decoded = is_string($data) ? base64_decode($data, true) : false;
        if ($decoded === false || ! str_starts_with($decoded, "\x89PNG\r\n\x1a\n")) {
            return response()->json(['message' => 'Il servizio ha restituito un risultato non valido.'], 502);
        }

        return response()->json(['image' => $data])->header('Cache-Control', 'no-store');
    }
}
