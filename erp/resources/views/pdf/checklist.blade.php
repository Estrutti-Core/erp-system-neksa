<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
@php
    $primaryColor = $company?->primary_color ?: '#4f46e5';

    $typeLabels = \App\Models\ChecklistQuestion::questionTypes();

    function answerLabel(string $type, ?string $val): string {
        if ($val === null || $val === '') return '—';
        if ($type === 'checkbox') return $val === 'sim' ? '✓ Sim / OK' : '✗ Não';
        return $val;
    }

    function photoBase64(string $path): ?string {
        $abs = storage_path('app/public/' . ltrim($path, '/'));
        if (!file_exists($abs)) return null;
        $mime = mime_content_type($abs) ?: 'image/jpeg';
        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($abs));
    }
@endphp
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size:9px; color:#1e293b; }

    /* ── Cabeçalho fixo ─────────────────────────────────────────────── */
    .pdf-header {
        position: fixed;
        top: 0; left: 0; right: 0;
        background: #fff;
        border-bottom: 2px solid {{ $primaryColor }};
        padding: 8px 22px;
    }
    .pdf-header table { width:100%; border-collapse:collapse; }
    .pdf-header td { vertical-align:middle; }
    .hd-logo { font-size:13px; font-weight:bold; color:#0f172a; }
    .hd-sub  { font-size:8px; color:#64748b; margin-top:2px; }
    .hd-right { text-align:right; }
    .hd-title { font-size:12px; font-weight:bold; color:#475569; }
    .hd-meta  { font-size:8px; color:#94a3b8; margin-top:3px; line-height:1.5; }

    /* ── Rodapé fixo ────────────────────────────────────────────────── */
    .pdf-footer {
        position: fixed;
        bottom: 0; left: 0; right: 0;
        border-top: 1px solid #e2e8f0;
        padding: 5px 22px;
        font-size: 7.5px;
        color: #94a3b8;
    }
    .pdf-footer table { width:100%; border-collapse:collapse; }
    .page-num::after { content: counter(page); }

    /* ── Conteúdo ───────────────────────────────────────────────────── */
    .pdf-body { margin-top: 62px; margin-bottom: 28px; padding: 0 22px; }

    /* ── Seção ──────────────────────────────────────────────────────── */
    .section-bar {
        background: #f1f5f9;
        border-left: 3px solid {{ $primaryColor }};
        padding: 5px 10px;
        margin-top: 16px;
        margin-bottom: 8px;
        page-break-after: avoid;
    }
    .section-bar .s-title { font-size:10px; font-weight:bold; color:#1e293b; text-transform:uppercase; letter-spacing:.06em; }
    .section-bar .s-desc  { font-size:8px; color:#64748b; margin-top:2px; }

    /* ── Grid de perguntas (2 colunas) ─────────────────────────────── */
    .question-grid { width:100%; border-collapse:collapse; }
    .question-grid td { vertical-align:top; padding:5px 6px; width:50%; }
    .q-box {
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 7px 9px;
        min-height: 40px;
        page-break-inside: avoid;
    }
    .q-label { font-size:8px; font-weight:bold; color:#64748b; text-transform:uppercase; letter-spacing:.04em; margin-bottom:3px; }
    .q-text  { font-size:9px; font-weight:bold; color:#1e293b; margin-bottom:4px; line-height:1.4; }
    .q-answer{ font-size:9px; color:#1e293b; font-weight:600; }
    .q-obs   { font-size:8px; color:#64748b; font-style:italic; margin-top:3px; border-top:1px solid #f1f5f9; padding-top:3px; }

    /* ── Label informativo ──────────────────────────────────────────── */
    .q-label-info {
        background:#fffbeb;
        border:1px solid #fde68a;
        border-radius:6px;
        padding:6px 10px;
        font-size:8.5px;
        color:#92400e;
        font-style:italic;
        margin-bottom:6px;
    }

    /* ── Galeria de fotos ───────────────────────────────────────────── */
    .photo-gallery { margin-top:6px; }
    .gallery-row   { width:100%; border-collapse:collapse; margin-bottom:4px; }
    .gallery-row td { width:25%; padding:2px; vertical-align:top; }
    .gallery-img   { width:100%; height:60px; object-fit:cover; border-radius:4px; border:1px solid #e2e8f0; }

    /* ── Assinatura (questão tipo signature) ────────────────────────── */
    .sig-img { max-width:140px; max-height:60px; border:1px solid #e2e8f0; border-radius:4px; }

    /* ── Resumo de preenchimento ────────────────────────────────────── */
    .summary-bar {
        background: {{ $primaryColor }};
        color: #fff;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 9px;
        font-weight: bold;
        margin-bottom: 12px;
    }
</style>
</head>
<body>

{{-- ── CABEÇALHO FIXO ──────────────────────────────────────────────────── --}}
<div class="pdf-header">
    <table>
        <tr>
            <td style="width:55%;">
                @if($company?->logo_path && file_exists(public_path('storage/' . $company->logo_path)))
                    <img src="{{ public_path('storage/' . $company->logo_path) }}"
                         style="max-height:32px;max-width:110px;margin-bottom:3px;"><br>
                @endif
                <div class="hd-logo">{{ $company?->name ?? 'Neksa ERP' }}</div>
                <div class="hd-sub">
                    @if($company?->document) CNPJ: {{ $company->document }} @endif
                    @if($company?->phone) | {{ $company->phone }} @endif
                </div>
            </td>
            <td class="hd-right">
                <div class="hd-title">RELATÓRIO DE CHECKLIST</div>
                <div class="hd-meta">
                    OS: <strong>{{ $serviceOrder->code }}</strong><br>
                    Checklist: <strong>{{ $checklist->template?->name ?? 'N/A' }}</strong><br>
                    Cliente: {{ $serviceOrder->client?->name }}<br>
                    Técnico: {{ $serviceOrder->technician?->name ?? '—' }}<br>
                    @if($checklist->filled_at)
                        Preenchido em: {{ $checklist->filled_at->format('d/m/Y H:i') }}
                    @else
                        Status: <span style="color:#dc2626;">Pendente</span>
                    @endif
                </div>
            </td>
        </tr>
    </table>
</div>

{{-- ── RODAPÉ FIXO ─────────────────────────────────────────────────────── --}}
<div class="pdf-footer">
    <table>
        <tr>
            <td>{{ $company?->name ?? 'Neksa ERP' }} · OS {{ $serviceOrder->code }} · {{ $checklist->template?->name }}</td>
            <td style="text-align:right;">
                Pág. <span class="page-num"></span> · Gerado em {{ now()->format('d/m/Y H:i') }}
            </td>
        </tr>
    </table>
</div>

{{-- ── CORPO ────────────────────────────────────────────────────────────── --}}
<div class="pdf-body">

    {{-- Barra de resumo --}}
    <div class="summary-bar">
        OS {{ $serviceOrder->code }}
        &nbsp;·&nbsp; {{ $checklist->template?->name ?? 'Checklist' }}
        @if($checklist->filled_at)
            &nbsp;·&nbsp; Preenchido em {{ $checklist->filled_at->format('d/m/Y \à\s H:i') }}
        @endif
        @if($serviceOrder->client)
            &nbsp;·&nbsp; {{ $serviceOrder->client->name }}
        @endif
    </div>

    @forelse($checklist->instancedSections->sortBy('order') as $section)

        {{-- Barra da seção --}}
        <div class="section-bar">
            <div class="s-title">{{ $section->title }}</div>
            @if($section->description)
                <div class="s-desc">{{ $section->description }}</div>
            @endif
        </div>

        @php
            $questions = $section->questions->sortBy('order');
            $pairs     = $questions->chunk(2);
        @endphp

        @foreach($pairs as $pair)
            @php $pArr = $pair->values(); @endphp

            @if($pArr->count() === 1 && $pArr[0]->question_type === 'label')
                {{-- Label ocupa linha inteira --}}
                <div class="q-label-info">{{ $pArr[0]->question_text }}</div>
            @else
                <table class="question-grid">
                    <tr>
                        @foreach($pArr as $question)
                            @php
                                $answer = $question->answer;
                                $photos = $answer?->photos_urls ?? [];
                            @endphp
                            <td>
                                @if($question->question_type === 'label')
                                    <div class="q-label-info">{{ $question->question_text }}</div>
                                @else
                                    <div class="q-box">
                                        <div class="q-label">
                                            {{ $typeLabels[$question->question_type] ?? $question->question_type }}
                                            @if($question->is_required) <span style="color:#dc2626;">*</span> @endif
                                        </div>
                                        <div class="q-text">{{ $question->question_text }}</div>

                                        @if($question->question_type === 'signature' && $answer?->answer_value && str_starts_with($answer->answer_value, 'data:'))
                                            <img class="sig-img" src="{{ $answer->answer_value }}" alt="Assinatura">
                                        @elseif($question->question_type === 'photo' || $question->question_type === 'drawing')
                                            @if(!empty($photos))
                                                <div class="q-answer" style="color:#16a34a;">✓ {{ count($photos) }} foto(s)</div>
                                            @else
                                                <div class="q-answer" style="color:#94a3b8;">Sem foto</div>
                                            @endif
                                        @else
                                            <div class="q-answer">{{ answerLabel($question->question_type, $answer?->answer_value) }}</div>
                                        @endif

                                        @if($answer?->observation)
                                            <div class="q-obs">Obs: {{ $answer->observation }}</div>
                                        @endif

                                        {{-- Fotos para tipos não-photo (evidências) --}}
                                        @if($question->question_type !== 'photo' && !empty($photos))
                                            <div class="photo-gallery">
                                                @foreach($photos->chunk(4) as $row)
                                                    <table class="gallery-row">
                                                        <tr>
                                                            @foreach($row as $photoUrl)
                                                                @php
                                                                    // Converte URL → path relativo → base64
                                                                    preg_match('#/storage/(.+)$#', $photoUrl, $m);
                                                                    $b64 = isset($m[1]) ? photoBase64($m[1]) : null;
                                                                @endphp
                                                                <td>
                                                                    @if($b64)
                                                                        <img class="gallery-img" src="{{ $b64 }}" alt="Foto">
                                                                    @endif
                                                                </td>
                                                            @endforeach
                                                            @for($pad = count($row); $pad < 4; $pad++)
                                                                <td></td>
                                                            @endfor
                                                        </tr>
                                                    </table>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        @endforeach

                        {{-- Célula vazia se seção tem número ímpar de perguntas --}}
                        @if($pArr->count() === 1)
                            <td></td>
                        @endif
                    </tr>
                </table>

                {{-- Fotos do tipo photo (galeria dedicada, linha inteira) --}}
                @foreach($pArr as $question)
                    @if($question->question_type === 'photo')
                        @php
                            $answer = $question->answer;
                            $photos = $answer?->photos_urls ?? [];
                        @endphp
                        @if(!empty($photos))
                            <div style="margin: 4px 6px 8px; padding:6px 9px; border:1px solid #e2e8f0; border-radius:6px;">
                                <div class="q-label" style="margin-bottom:5px;">{{ $question->question_text }}</div>
                                <table class="gallery-row">
                                    <tr>
                                        @foreach(array_slice($photos, 0, 4) as $photoUrl)
                                            @php
                                                preg_match('#/storage/(.+)$#', $photoUrl, $m);
                                                $b64 = isset($m[1]) ? photoBase64($m[1]) : null;
                                            @endphp
                                            <td>
                                                @if($b64)
                                                    <img class="gallery-img" src="{{ $b64 }}" alt="Foto"
                                                         style="height:90px;">
                                                @endif
                                            </td>
                                        @endforeach
                                        @for($pad = min(count($photos), 4); $pad < 4; $pad++)
                                            <td></td>
                                        @endfor
                                    </tr>
                                </table>
                                @if($answer?->observation)
                                    <div class="q-obs" style="margin-top:4px;">Obs: {{ $answer->observation }}</div>
                                @endif
                            </div>
                        @endif
                    @endif
                @endforeach
            @endif
        @endforeach

    @empty
        <p style="text-align:center;color:#94a3b8;font-style:italic;margin-top:40px;">
            Checklist sem perguntas.
        </p>
    @endforelse

</div>
</body>
</html>
