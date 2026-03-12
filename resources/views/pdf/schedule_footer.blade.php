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

<div class="legend-wrap" style="margin-top:20px">
    @php
        $legendRows = $legend_items
            ->filter(function ($item) {
                return strtoupper((string) $item->code) !== 'OFF';
            })
            ->values()
            ->chunk(2);
    @endphp
    <table class="legend">
        <thead>
        <tr>
            <th colspan="4" style="background: #f1c40f">LEGEND</th>
        </tr>
        <tr>
            <th>CODE</th>
            <th>TIME</th>
            <th>CODE</th>
            <th>TIME</th>
        </tr>
        </thead>
        <tbody>
        @foreach($legendRows as $row)
            <tr>
                @foreach($row as $item)
                    @php
                        $start = $item->time_in === $item->time_in_latest
                            ? \Carbon\Carbon::parse($item->time_in)->format('h:i A')
                            : \Carbon\Carbon::parse($item->time_in)->format('h:i A') . '-' . \Carbon\Carbon::parse($item->time_in_latest)->format('h:i A');
                        $end = $item->time_out === $item->time_out_latest
                            ? \Carbon\Carbon::parse($item->time_out)->format('h:i A')
                            : \Carbon\Carbon::parse($item->time_out)->format('h:i A') . '-' . \Carbon\Carbon::parse($item->time_out_latest)->format('h:i A');
                        $time = $start . ' - ' . $end;
                    @endphp
                    <td class="code">{{ strtoupper($item->code) }}</td>
                    <td class="time">{{ $time }}</td>
                @endforeach
                @if($row->count() < 2)
                    <td class="code">&nbsp;</td>
                    <td class="time">&nbsp;</td>
                @endif
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
</div>
