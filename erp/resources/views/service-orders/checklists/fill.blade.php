@extends('layouts.app')
@section('title', 'Preencher Checklist — OS ' . $serviceOrder->code)

@section('content')
<div style="max-width: 680px; margin: 0 auto; padding-bottom: 60px;">

    {{-- Cabeçalho --}}
    <div class="flex items-center gap-3 mb-4">
        <a href="{{ route('service-orders.show', $serviceOrder) }}" class="btn btn-secondary btn-sm">← Voltar</a>
        <div>
            <div class="font-bold text-lg">{{ $checklist->template->name }}</div>
            <div class="text-sm text-muted">OS {{ $serviceOrder->code }} — {{ $serviceOrder->client->name }}</div>
        </div>
    </div>

    @if($checklist->isFilled())
        <div class="alert alert-success mb-4" style="background:#dcfce7;border:1px solid #86efac;border-radius:10px;padding:12px 16px;color:#166534;font-weight:600;">
            Este checklist foi preenchido em {{ $checklist->filled_at->format('d/m/Y H:i') }}.
            Você pode editá-lo novamente se necessário.
        </div>
    @endif

    <form action="{{ route('service-orders.checklists.save', [$serviceOrder, $checklist]) }}"
          method="POST"
          enctype="multipart/form-data"
          id="checklist-form">
        @csrf
        @method('PUT')

        @foreach($checklist->instancedQuestions as $question)
            @php
                $answer = $question->answer;
                $qId    = $question->id;
            @endphp

            <div class="card mb-3" style="border-radius:12px;border:1px solid #e2e8f0;padding:18px 16px;">

                {{-- Label Informativo --}}
                @if($question->question_type === 'label')
                    <div style="display:flex;align-items:flex-start;gap:10px;">
                        <div style="width:24px;height:24px;border-radius:6px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#64748b" style="width:14px;height:14px"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
                        </div>
                        <p style="margin:0;font-size:14px;color:#475569;font-style:italic;line-height:1.5;">{{ $question->question_text }}</p>
                    </div>

                @else
                    {{-- Pergunta com resposta --}}
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

                    @if($question->question_type === 'text')
                        <textarea name="answers[{{ $qId }}][value]"
                            class="form-control"
                            rows="3"
                            placeholder="Digite a resposta..."
                            {{ $question->is_required ? 'required' : '' }}
                            style="font-size:15px;">{{ old("answers.{$qId}.value", $answer?->answer_value) }}</textarea>

                    @elseif($question->question_type === 'checkbox')
                        <div style="display:flex;align-items:center;gap:12px;padding:10px 0;">
                            <input type="hidden" name="answers[{{ $qId }}][value]" value="não">
                            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:16px;font-weight:600;color:#1e293b;">
                                <input type="checkbox"
                                    name="answers[{{ $qId }}][value]"
                                    value="sim"
                                    {{ $answer?->answer_value === 'sim' ? 'checked' : '' }}
                                    style="width:22px;height:22px;cursor:pointer;accent-color:#6366f1;">
                                Confirmar / Marcar como OK
                            </label>
                        </div>

                    @elseif($question->question_type === 'select')
                        <select name="answers[{{ $qId }}][value]"
                            class="form-control"
                            style="font-size:15px;height:48px;"
                            {{ $question->is_required ? 'required' : '' }}>
                            <option value="">Selecione uma opção...</option>
                            @foreach($question->options as $opt)
                                <option value="{{ $opt }}"
                                    {{ $answer?->answer_value === $opt ? 'selected' : '' }}>
                                    {{ $opt }}
                                </option>
                            @endforeach
                        </select>

                    @elseif($question->question_type === 'number')
                        <input type="number" name="answers[{{ $qId }}][value]"
                            class="form-control"
                            style="font-size:18px;height:52px;text-align:center;font-weight:700;"
                            placeholder="0"
                            value="{{ old("answers.{$qId}.value", $answer?->answer_value) }}"
                            {{ $question->is_required ? 'required' : '' }}>

                    @elseif($question->question_type === 'photo')
                        @if($answer?->photo_path)
                            <div class="mb-2">
                                <img src="{{ $answer->photo_url }}" alt="Foto atual"
                                    style="max-width:100%;border-radius:8px;border:1px solid #e2e8f0;max-height:200px;object-fit:cover;">
                                <p class="text-xs text-muted mt-1">Foto atual — envie outra para substituir</p>
                            </div>
                        @endif
                        <label style="display:block;background:#f0f9ff;border:2px dashed #38bdf8;border-radius:10px;padding:24px;text-align:center;cursor:pointer;font-size:14px;color:#0369a1;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:28px;height:28px;margin:0 auto 6px;display:block;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                            </svg>
                            Toque para tirar foto ou selecionar arquivo
                            <input type="file" name="answers[{{ $qId }}][photo]"
                                accept="image/*" capture="environment"
                                style="display:none;"
                                {{ $question->is_required && !$answer?->photo_path ? 'required' : '' }}>
                        </label>

                    @elseif($question->question_type === 'drawing')
                        <div>
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
                        </div>
                    @endif

                    @error("answers.{$qId}.value")
                        <div class="invalid-feedback" style="display:block;">{{ $message }}</div>
                    @enderror
                @endif
            </div>
        @endforeach

        <div class="flex gap-3 justify-end mt-4">
            <a href="{{ route('service-orders.show', $serviceOrder) }}" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"
                style="background:linear-gradient(135deg,#6366f1,#4f46e5);padding:12px 28px;font-size:16px;font-weight:700;">
                Salvar Respostas
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
// ── Desenho em Canvas ─────────────────────────────────────────────────────────
document.querySelectorAll('canvas[id^="drawing-"]').forEach(canvas => {
    const qId    = canvas.id.replace('drawing-', '');
    const input  = document.getElementById('drawing-data-' + qId);
    const ctx    = canvas.getContext('2d');
    let drawing  = false;
    let lastX = 0, lastY = 0;

    // Restaurar desenho existente
    if (input.value && input.value.startsWith('data:')) {
        const img = new Image();
        img.onload = () => ctx.drawImage(img, 0, 0);
        img.src = input.value;
    }

    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;
        const src = e.touches ? e.touches[0] : e;
        return {
            x: (src.clientX - rect.left) * scaleX,
            y: (src.clientY - rect.top) * scaleY
        };
    }

    function startDraw(e) {
        e.preventDefault();
        drawing = true;
        const pos = getPos(e);
        lastX = pos.x; lastY = pos.y;
    }

    function draw(e) {
        e.preventDefault();
        if (!drawing) return;
        const pos = getPos(e);
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(pos.x, pos.y);
        ctx.strokeStyle = '#1e293b';
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.stroke();
        lastX = pos.x; lastY = pos.y;
    }

    function endDraw() {
        if (drawing) {
            drawing = false;
            input.value = canvas.toDataURL('image/png');
        }
    }

    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', endDraw);
    canvas.addEventListener('touchstart', startDraw, { passive: false });
    canvas.addEventListener('touchmove', draw, { passive: false });
    canvas.addEventListener('touchend', endDraw);
});

function clearDrawing(qId) {
    const canvas = document.getElementById('drawing-' + qId);
    const input  = document.getElementById('drawing-data-' + qId);
    canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
    input.value = '';
}

// ── Preview foto selecionada ──────────────────────────────────────────────────
document.querySelectorAll('input[type="file"]').forEach(input => {
    input.addEventListener('change', function () {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                let preview = this.parentElement.querySelector('.photo-preview');
                if (!preview) {
                    preview = document.createElement('img');
                    preview.className = 'photo-preview';
                    preview.style.cssText = 'max-width:100%;border-radius:8px;margin-top:8px;border:1px solid #e2e8f0;max-height:200px;object-fit:cover;';
                    this.parentElement.insertAdjacentElement('afterend', preview);
                }
                preview.src = e.target.result;
            };
            reader.readAsDataURL(this.files[0]);
        }
    });
});
</script>
@endpush
@endsection
