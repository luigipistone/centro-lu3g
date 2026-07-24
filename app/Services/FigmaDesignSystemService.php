<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class FigmaDesignSystemService
{
    public function __construct(private readonly FigmaService $figma) {}

    public function analyze(object $project, string $userId): array
    {
        $file = $this->figma->file($project->figma_file_key);
        $colors = [];
        $fonts = [];
        $this->collectNode($file['document'] ?? [], $colors, $fonts);

        $palette = $this->buildPalette($colors);
        $typography = $this->buildTypography($fonts);

        if (count($palette) < 2) {
            throw new RuntimeException('Nel file Figma non sono stati rilevati abbastanza colori utilizzabili.');
        }
        if ($typography === []) {
            throw new RuntimeException('Nel file Figma non sono stati rilevati stili tipografici utilizzabili.');
        }

        $row = [
            'figma_file_key' => $project->figma_file_key,
            'colors' => json_encode($palette, JSON_UNESCAPED_SLASHES),
            'typography' => json_encode($typography, JSON_UNESCAPED_SLASHES),
            'status' => 'analyzed',
            'error_message' => null,
            'analyzed_by' => $userId,
            'analyzed_at' => now(),
            'applied_by' => null,
            'applied_at' => null,
            'updated_at' => now(),
        ];

        $existingId = DB::table('figma_design_systems')->where('project_id', $project->id)->value('id');
        if ($existingId) {
            DB::table('figma_design_systems')->where('id', $existingId)->update($row);
        } else {
            DB::table('figma_design_systems')->insert([
                'id' => (string) Str::uuid(),
                'project_id' => $project->id,
                ...$row,
                'created_at' => now(),
            ]);
        }

        return $this->publicRow(DB::table('figma_design_systems')->where('project_id', $project->id)->first());
    }

    public function apply(object $project, object $provisioning, array $colors, array $typography, string $userId): array
    {
        $payload = [
            'colors' => $this->validateColors($colors),
            'typography' => $this->validateTypography($typography),
        ];

        $result = Process::timeout(180)->run([
            'sudo',
            (string) config('wordpress-provisioning.runner'),
            'apply-elementor-design',
            '--folder',
            $provisioning->folder_slug,
            '--payload',
            base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES)),
        ]);

        if ($result->failed()) {
            $message = trim($result->errorOutput() ?: $result->output()) ?: 'Applicazione del design system non riuscita.';
            DB::table('figma_design_systems')->where('project_id', $project->id)->update([
                'status' => 'failed',
                'error_message' => Str::limit($message, 4000, ''),
                'updated_at' => now(),
            ]);
            throw new RuntimeException($message);
        }

        DB::table('figma_design_systems')->where('project_id', $project->id)->update([
            'colors' => json_encode($payload['colors'], JSON_UNESCAPED_SLASHES),
            'typography' => json_encode($payload['typography'], JSON_UNESCAPED_SLASHES),
            'status' => 'applied',
            'error_message' => null,
            'applied_by' => $userId,
            'applied_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->publicRow(DB::table('figma_design_systems')->where('project_id', $project->id)->first());
    }

    public function current(string $projectId): ?array
    {
        $row = DB::table('figma_design_systems')->where('project_id', $projectId)->first();

        return $row ? $this->publicRow($row) : null;
    }

    private function collectNode(array $node, array &$colors, array &$fonts): void
    {
        if (($node['visible'] ?? true) === false) {
            return;
        }

        foreach ($node['fills'] ?? [] as $fill) {
            if (($fill['type'] ?? null) !== 'SOLID' || ($fill['visible'] ?? true) === false) {
                continue;
            }
            $opacity = (float) ($fill['opacity'] ?? 1);
            if ($opacity < 0.5 || ! isset($fill['color'])) {
                continue;
            }
            $hex = $this->figmaColorToHex($fill['color']);
            $colors[$hex] = ($colors[$hex] ?? 0) + (($node['type'] ?? '') === 'TEXT' ? 3 : 1);
        }

        if (($node['type'] ?? null) === 'TEXT' && isset($node['style']['fontFamily'])) {
            $family = trim((string) $node['style']['fontFamily']);
            $weight = (int) ($node['style']['fontWeight'] ?? 400);
            $size = (float) ($node['style']['fontSize'] ?? 16);
            $key = $family.'|'.$weight.'|'.$size;
            $fonts[$key] = ($fonts[$key] ?? 0) + max(1, mb_strlen((string) ($node['characters'] ?? '')));
        }

        foreach ($node['children'] ?? [] as $child) {
            if (is_array($child)) {
                $this->collectNode($child, $colors, $fonts);
            }
        }
    }

    private function buildPalette(array $colors): array
    {
        arsort($colors);
        $ranked = collect($colors)->map(fn ($count, $hex) => [
            'hex' => $hex,
            'count' => $count,
            'luminance' => $this->luminance($hex),
            'saturation' => $this->saturation($hex),
        ])->values();

        $text = $ranked->where('luminance', '<', 0.32)->sortByDesc('count')->first()
            ?? $ranked->sortBy('luminance')->first();
        $chromatic = $ranked
            ->where('saturation', '>', 0.18)
            ->where('luminance', '>', 0.04)
            ->where('luminance', '<', 0.9)
            ->sortByDesc('count')
            ->values();
        $primary = $chromatic->first() ?? $ranked->first();
        $secondary = $chromatic->first(fn ($item) => $item['hex'] !== $primary['hex'])
            ?? $ranked->first(fn ($item) => $item['hex'] !== $primary['hex']);
        $accent = $chromatic->first(fn ($item) => ! in_array($item['hex'], [$primary['hex'], $secondary['hex'] ?? null], true))
            ?? $secondary ?? $primary;

        return [
            'primary' => $primary['hex'],
            'secondary' => ($secondary ?? $primary)['hex'],
            'text' => $text['hex'],
            'accent' => $accent['hex'],
            'detected' => $ranked->take(12)->pluck('hex')->all(),
        ];
    }

    private function buildTypography(array $fonts): array
    {
        arsort($fonts);
        $styles = collect($fonts)->map(function ($count, $key) {
            [$family, $weight, $size] = explode('|', $key);

            return ['family' => $family, 'weight' => (int) $weight, 'size' => (float) $size, 'count' => $count];
        })->values();

        if ($styles->isEmpty()) {
            return [];
        }

        $body = $styles->sortByDesc('count')->first();
        $heading = $styles->sortByDesc(fn ($style) => $style['size'] * max(1, $style['weight'] / 400))->first();
        $secondary = $styles->where('family', $heading['family'])->sortByDesc('size')->skip(1)->first() ?? $body;

        return [
            'primary' => ['family' => $heading['family'], 'weight' => max(600, $heading['weight'])],
            'secondary' => ['family' => $secondary['family'], 'weight' => max(500, $secondary['weight'])],
            'text' => ['family' => $body['family'], 'weight' => $body['weight']],
            'accent' => ['family' => $heading['family'], 'weight' => max(500, min(700, $heading['weight']))],
            'detected' => $styles->take(12)->all(),
        ];
    }

    private function validateColors(array $colors): array
    {
        $result = [];
        foreach (['primary', 'secondary', 'text', 'accent'] as $role) {
            $value = strtoupper(trim((string) ($colors[$role] ?? '')));
            if (! preg_match('/^#[0-9A-F]{6}$/', $value)) {
                throw new RuntimeException("Colore {$role} non valido.");
            }
            $result[$role] = $value;
        }

        return $result;
    }

    private function validateTypography(array $typography): array
    {
        $result = [];
        foreach (['primary', 'secondary', 'text', 'accent'] as $role) {
            $family = trim((string) ($typography[$role]['family'] ?? ''));
            $weight = (int) ($typography[$role]['weight'] ?? 400);
            if ($family === '' || mb_strlen($family) > 120 || $weight < 100 || $weight > 900) {
                throw new RuntimeException("Tipografia {$role} non valida.");
            }
            $result[$role] = ['family' => $family, 'weight' => $weight];
        }

        return $result;
    }

    private function figmaColorToHex(array $color): string
    {
        return sprintf('#%02X%02X%02X',
            (int) round(max(0, min(1, $color['r'] ?? 0)) * 255),
            (int) round(max(0, min(1, $color['g'] ?? 0)) * 255),
            (int) round(max(0, min(1, $color['b'] ?? 0)) * 255),
        );
    }

    private function luminance(string $hex): float
    {
        $rgb = array_map(fn ($offset) => hexdec(substr($hex, $offset, 2)) / 255, [1, 3, 5]);
        $rgb = array_map(fn ($value) => $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4, $rgb);

        return 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2];
    }

    private function saturation(string $hex): float
    {
        $rgb = array_map(fn ($offset) => hexdec(substr($hex, $offset, 2)) / 255, [1, 3, 5]);
        $max = max($rgb);
        $min = min($rgb);

        return $max === $min ? 0 : ($max - $min) / (1 - abs($max + $min - 1));
    }

    public function publicRow(object $row): array
    {
        return [
            'id' => $row->id,
            'figma_file_key' => $row->figma_file_key,
            'colors' => json_decode($row->colors, true) ?: [],
            'typography' => json_decode($row->typography, true) ?: [],
            'status' => $row->status,
            'error_message' => $row->error_message,
            'analyzed_at' => $row->analyzed_at,
            'applied_at' => $row->applied_at,
        ];
    }
}
