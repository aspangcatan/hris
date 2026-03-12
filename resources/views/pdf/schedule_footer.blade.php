<div class="{{ !empty($footer_fixed) ? 'footer-fixed' : '' }}">
    <div class="signatures">
        <div class="sig-block sig-indent">
            <div class="sig-label">Prepared by:</div>
            <div class="sig-space"></div>
            <div class="sig-name">{{ strtoupper($prepared_by) }}</div>
            <div class="sig-title">{{ $prepared_title }}</div>
        </div>
        <div class="sig-block sig-indent">
            <div class="sig-label">Approved by:</div>
            <div class="sig-space"></div>
            <div class="sig-name">{{ strtoupper($approved_by) }}</div>
            <div class="sig-title">{{ $approved_title }}</div>
        </div>
    </div>
</div>