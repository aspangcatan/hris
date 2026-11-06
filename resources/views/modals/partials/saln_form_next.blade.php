{{-- "Next section" link at the bottom of a SALN form pane. $next = ['board' => ..., 'pane' => ..., 'label' => ...] or null. --}}
@if($next)
    <div class="sf-next-row">
        <button type="button" class="btn btn-light btn-sm sf-next" data-sf-board="tab_{{ $next['board'] }}" data-sf-pane="tab_{{ $next['pane'] }}">
            Next: {{ $next['label'] }}<i class="bi bi-arrow-right ms-1"></i>
        </button>
    </div>
@else
    <div class="sf-next-row">
        <span class="sf-done"><i class="bi bi-check2-circle me-1"></i>That's the last section. Click <strong>Save declaration</strong> when you're done.</span>
    </div>
@endif
