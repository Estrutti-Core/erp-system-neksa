@extends('layouts.app')
@section('title', 'Preencher Checklist — OS ' . $serviceOrder->code)

@section('content')
<div style="max-width: 720px; margin: 0 auto; padding-bottom: 80px;">

    {{-- Cabeçalho --}}
    <div class="flex items-center gap-3 mb-4">
        <a href="{{ route('service-orders.show', $serviceOrder) }}" class="btn btn-secondary btn-sm">← Voltar</a>
        <div>
            <div class="font-bold text-lg">{{ $checklist->template?->name ?? 'Checklist' }}</div>
            <div class="text-sm text-muted">OS {{ $serviceOrder->code }} — {{ $serviceOrder->client->name }}</div>
        </div>
        @if($checklist->isFilled())
            <a href="{{ route('service-orders.checklists.pdf', [$serviceOrder, $checklist]) }}"
               target="_blank"
               class="btn btn-secondary btn-sm"
               style="margin-left:auto;gap:6px;display:flex;align-items:center;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                PDF
            </a>
        @endif
    </div>

    @if($checklist->isFilled())
        <div style="background:#dcfce7;border:1px solid #86efac;border-radius:10px;padding:12px 16px;color:#166534;font-weight:600;margin-bottom:16px;">
            Preenchido em {{ $checklist->filled_at->format('d/m/Y H:i') }}. Você pode editar novamente se necessário.
        </div>
    @endif

    <form action="{{ route('service-orders.checklists.save', [$serviceOrder, $checklist]) }}"
          method="POST" enctype="multipart/form-data" id="checklist-form">
        @csrf
        @method('PUT')

        @forelse($checklist->instancedSections->sortBy('order') as $section)
            {{-- Cabeçalho da seção --}}
            <div style="margin-bottom:6px; margin-top:{{ $loop->first ? '0' : '28px' }}; border-bottom:2px solid #e2e8f0; padding-bottom:8px;">
                <h3 style="margin:0; font-size:15px; font-weight:700; color:#1e293b; letter-spacing:.01em;">
                    {{ $section->title }}
                </h3>
                @if($section->description)
                    <p style="margin:4px 0 0; font-size:13px; color:#64748b;">{{ $section->description }}</p>
                @endif
            </div>

            @forelse($section->questions->sortBy('order') as $question)
                @php
                    $answer = $question->answer;
                    $qId    = $question->id;
                @endphp

                <div class="card mb-3" style="border-radius:12px;border:1px solid #e2e8f0;padding:18px 16px;">

                    @if($question->question_type === 'label')
                        {{-- Rótulo informativo --}}
                        <div style="display:flex;align-items:flex-start;gap:10px;">
                            <div style="width:24px;height:24px;border-radius:6px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#64748b" style="width:14px;height:14px"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
                            </div>
                            <p style="margin:0;font-size:14px;color:#475569;font-style:italic;line-height:1.5;">{{ $question->question_text }}</p>
                        </div>

                    @else
                        {{-- Label da pergunta --}}
                        <div class="mb-3">
                            <label style="font-size:14px;font-weight:600;color:#1e293b;display:block;margin-bottom:4px;">
                                {{ $question->question_text }}
                                @if($question->is_required)
                                    <span style="color:#dc2626;font-size:13px;">*</span>
                                @endif
                            </label>
                            <span style="font-size:11px;font-weight:500;color:#64748b;background:#f1f5f9;padding:2px 8px;border-radius:20px;">
                                {{ \App\Models\ChecklistQuestion::questionTypes()[$question->question_type] ?? $question->question_type }}
                            </span>
                        </div>

                        {{-- Input principal por tipo --}}
                        @if($question->question_type === 'text')
                            <textarea name="answers[{{ $qId }}][value]" class="form-control" rows="3"
                                placeholder="Digite a resposta..."
                                {{ $question->is_required ? 'required' : '' }}
                                style="font-size:15px;">{{ old("answers.{$qId}.value", $answer?->answer_value) }}</textarea>

                        @elseif($question->question_type === 'checkbox')
                            <div style="display:flex;align-items:center;gap:12px;padding:10px 0;">
                                <input type="hidden" name="answers[{{ $qId }}][value]" value="não">
                                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:16px;font-weight:600;color:#1e293b;">
                                    <input type="checkbox" name="answers[{{ $qId }}][value]" value="sim"
                                        {{ $answer?->answer_value === 'sim' ? 'checked' : '' }}
                                        style="width:22px;height:22px;cursor:pointer;accent-color:#6366f1;">
                                    Confirmar / Marcar como OK
                                </label>
                            </div>

                        @elseif($question->question_type === 'select')
                            <select name="answers[{{ $qId }}][value]" class="form-control"
                                style="font-size:15px;height:48px;"
                                {{ $question->is_required ? 'required' : '' }}>
                                <option value="">Selecione uma opção...</option>
                                @foreach($question->options as $opt)
                                    <option value="{{ $opt }}" {{ $answer?->answer_value === $opt ? 'selected' : '' }}>
                                        {{ $opt }}
                                    </option>
                                @endforeach
                            </select>

                        @elseif($question->question_type === 'photo')
                            @php $existingUrls = $answer?->photos_urls ?? []; @endphp
                            @if(!empty($existingUrls))
                                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
                                    @foreach($existingUrls as $url)
                                        <img src="{{ $url }}" alt="Foto"
                                            style="width:90px;height:90px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;">
                                    @endforeach
                                </div>
                                <p class="text-xs text-muted mb-2">{{ count($existingUrls) }} foto(s) salva(s). Envie novas para adicionar (máx. 5 no total).</p>
                            @endif
                            @php $remaining = max(0, 5 - count($existingUrls)); @endphp
                            @if($remaining > 0)
                                <label class="photo-upload-label" style="display:block;background:#f0f9ff;border:2px dashed #38bdf8;border-radius:10px;padding:20px;text-align:center;cursor:pointer;font-size:14px;color:#0369a1;">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:28px;height:28px;margin:0 auto 6px;display:block;"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" /></svg>
                                    Toque para tirar foto (máx. {{ $remaining }})
                                    <input type="file" name="answers[{{ $qId }}][photos][]"
                                        accept="image/*" capture="environment" multiple
                                        data-max="{{ $remaining }}"
                                        style="display:none;"
                                        {{ $question->is_required && empty($existingUrls) ? 'required' : '' }}>
                                </label>
                                <div class="photo-preview-grid" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;"></div>
                            @endif

                        @elseif($question->question_type === 'drawing')
                            <canvas id="drawing-{{ $qId }}"
                                style="width:100%;border:2px solid #e2e8f0;border-radius:10px;background:#fff;touch-action:none;cursor:crosshair;"
                                height="200"></canvas>
                            <input type="hidden" name="answers[{{ $qId }}][value]" id="drawing-data-{{ $qId }}"
                                value="{{ $answer?->answer_value }}">
                            <div class="flex gap-2 mt-2">
                                <button type="button" class="btn btn-secondary btn-sm"
                                    onclick="clearDrawing('{{ $qId }}')">Limpar</button>
                                <span class="text-xs text-muted" style="padding-top:7px;">Use o dedo ou caneta para desenhar</span>
                            </div>

                        @elseif($question->question_type === 'signature')
                            <canvas id="signature-{{ $qId }}"
                                style="width:100%;border:2px solid #e2e8f0;border-radius:10px;background:#fff;touch-action:none;cursor:crosshair;"
                                height="160"></canvas>
                            <input type="hidden" name="answers[{{ $qId }}][value]" id="signature-data-{{ $qId }}"
                                value="{{ $answer?->answer_value }}">
                            <div class="flex gap-2 mt-2">
                                <button type="button" class="btn btn-secondary btn-sm"
                                    onclick="clearDrawing('{{ $qId }}', 'signature-')">Limpar</button>
                                @if($answer?->answer_value && str_starts_with($answer->answer_value, 'data:'))
                                    <span class="text-xs" style="padding-top:7px;color:#16a34a;">✓ Assinatura salva</span>
                                @endif
                            </div>
                        @endif

                        @error("answers.{$qId}.value")
                            <div class="invalid-feedback" style="display:block;">{{ $message }}</div>
                        @enderror

                        {{-- ── Evidências: observação + fotos (para todos os tipos não-photo) ── --}}
                        @if($question->question_type !== 'photo')
                            @php
                                $existingEvUrls = $answer?->photos_urls ?? [];
                                $evRemaining    = max(0, 5 - count($existingEvUrls));
                            @endphp
                            <div style="margin-top:16px;border-top:1px solid #f1f5f9;padding-top:14px;">
                                <div style="display:flex;align-items:center;gap:6px;margin-bottom:10px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#94a3b8" style="width:14px;height:14px"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 0 1 1.037-.443 48.282 48.282 0 0 0 5.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" /></svg>
                                    <span style="font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;">Observação e Fotos</span>
                                </div>

                                {{-- Observação --}}
                                <textarea name="answers[{{ $qId }}][observation]"
                                    class="form-control"
                                    rows="2"
                                    placeholder="Observações sobre este item (opcional)..."
                                    style="font-size:13px;color:#475569;resize:vertical;">{{ old("answers.{$qId}.observation", $answer?->observation) }}</textarea>

                                {{-- Fotos existentes --}}
                                @if(!empty($existingEvUrls))
                                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;">
                                        @foreach($existingEvUrls as $url)
                                            <img src="{{ $url }}" alt="Foto evidência"
                                                style="width:72px;height:72px;object-fit:cover;border-radius:6px;border:1px solid #e2e8f0;">
                                        @endforeach
                                    </div>
                                    @if(count($existingEvUrls) >= 5)
                                        <p style="font-size:11px;color:#94a3b8;margin-top:6px;">Limite de 5 fotos atingido.</p>
                                    @endif
                                @endif

                                {{-- Upload de novas fotos --}}
                                @if($evRemaining > 0)
                                    <label class="photo-upload-label" style="display:inline-flex;align-items:center;gap:6px;margin-top:10px;padding:8px 14px;border:1.5px dashed #cbd5e1;border-radius:8px;cursor:pointer;font-size:12px;color:#64748b;">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                                        Adicionar fotos ({{ $evRemaining }} restante{{ $evRemaining > 1 ? 's' : '' }})
                                        <input type="file" name="answers[{{ $qId }}][photos][]"
                                            accept="image/*" multiple
                                            data-max="{{ $evRemaining }}"
                                            style="display:none;">
                                    </label>
                                    <div class="photo-preview-grid" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;"></div>
                                @endif
                            </div>
                        @endif

                    @endif
                </div>
            @empty
                <p class="text-muted text-sm" style="padding-left:4px;">Nenhuma pergunta nesta seção.</p>
            @endforelse

        @empty
            <div class="card text-center text-muted py-6" style="font-style:italic;">
                Este checklist não possui perguntas.
            </div>
        @endforelse

        <div class="flex gap-3 justify-end mt-6">
            <a href="{{ route('service-orders.show', $serviceOrder) }}" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"
                style="padding:12px 28px;font-size:16px;font-weight:700;">
                Salvar Respostas
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function initCanvas(canvasId, inputId) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const input  = document.getElementById(inputId);
    const ctx    = canvas.getContext('2d');
    let drawing  = false, lastX = 0, lastY = 0;

    if (input && input.value && input.value.startsWith('data:')) {
        const img = new Image();
        img.onload = () => ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        img.src = input.value;
    }

    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        const sx = canvas.width / rect.width, sy = canvas.height / rect.height;
        const src = e.touches ? e.touches[0] : e;
        return { x: (src.clientX - rect.left) * sx, y: (src.clientY - rect.top) * sy };
    }

    canvas.addEventListener('mousedown',  e => { e.preventDefault(); drawing = true; const p = getPos(e); lastX = p.x; lastY = p.y; });
    canvas.addEventListener('touchstart', e => { e.preventDefault(); drawing = true; const p = getPos(e); lastX = p.x; lastY = p.y; }, { passive: false });
    canvas.addEventListener('mousemove',  e => { e.preventDefault(); if (!drawing) return; const p = getPos(e); ctx.beginPath(); ctx.moveTo(lastX, lastY); ctx.lineTo(p.x, p.y); ctx.strokeStyle = '#1e293b'; ctx.lineWidth = 2.5; ctx.lineCap = 'round'; ctx.stroke(); lastX = p.x; lastY = p.y; });
    canvas.addEventListener('touchmove',  e => { e.preventDefault(); if (!drawing) return; const p = getPos(e); ctx.beginPath(); ctx.moveTo(lastX, lastY); ctx.lineTo(p.x, p.y); ctx.strokeStyle = '#1e293b'; ctx.lineWidth = 2.5; ctx.lineCap = 'round'; ctx.stroke(); lastX = p.x; lastY = p.y; }, { passive: false });
    canvas.addEventListener('mouseup',  () => { if (drawing && input) { drawing = false; input.value = canvas.toDataURL('image/png'); } });
    canvas.addEventListener('touchend', () => { if (drawing && input) { drawing = false; input.value = canvas.toDataURL('image/png'); } });
}

function clearDrawing(qId, prefix = 'drawing-') {
    const canvas = document.getElementById(prefix + qId);
    const input  = document.getElementById(prefix + 'data-' + qId);
    if (canvas) canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
    if (input)  input.value = '';
}

document.querySelectorAll('canvas[id^="drawing-"]').forEach(c => {
    const qId = c.id.replace('drawing-', '');
    initCanvas('drawing-' + qId, 'drawing-data-' + qId);
});

document.querySelectorAll('canvas[id^="signature-"]').forEach(c => {
    const qId = c.id.replace('signature-', '');
    initCanvas('signature-' + qId, 'signature-data-' + qId);
});

// Múltiplas fotos com preview e limite
document.querySelectorAll('input[type="file"][data-max]').forEach(input => {
    input.addEventListener('change', function () {
        const max  = parseInt(this.dataset.max, 10);
        const grid = this.closest('label').nextElementSibling;
        if (!grid) return;
        grid.innerHTML = '';

        const files = Array.from(this.files).slice(0, max);
        if (files.length < this.files.length) {
            alert('Máximo de ' + max + ' foto(s) permitido(s). Apenas as primeiras ' + max + ' foram selecionadas.');
        }

        // Cria DataTransfer para sobrescrever FileList com apenas as permitidas
        const dt = new DataTransfer();
        files.forEach(f => dt.items.add(f));
        this.files = dt.files;

        files.forEach(file => {
            const reader = new FileReader();
            reader.onload = e => {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.style.cssText = 'width:72px;height:72px;object-fit:cover;border-radius:6px;border:1px solid #e2e8f0;';
                grid.appendChild(img);
            };
            reader.readAsDataURL(file);
        });
    });
});
</script>
@endpush
@endsection
