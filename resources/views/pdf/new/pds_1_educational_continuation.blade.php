@php
    if (!function_exists('schoolAdjustFontSize')) {
        function schoolAdjustFontSize($text, $baseSize = 10) {
            $length = strlen($text);
            if ($length > 45) return 'font-size: 7px;';
            if ($length > 35) return 'font-size: 8px;';
            if ($length > 13) return 'font-size: 10px;';
            return "font-size: {$baseSize}px;";
        }
    }

    if (!function_exists('honorsAdjustFontSize')) {
        function honorsAdjustFontSize($text, $baseSize = 13) {
            $length = strlen($text);
            if ($length > 15) return 'font-size: 4px;';
            if ($length > 10) return 'font-size: 5px;';
            return "font-size: {$baseSize}px;";
        }
    }
@endphp

<div id="page_educational_background" style="border:1px solid black">    
    <table style="border-top: solid 1px black">
        <tr style="background: gray">
            <td colspan="8" class="title">III. EDUCATIONAL BACKGROUND</td>
        </tr>
        <tr style="text-align: center" class="gray">
            <td rowspan="2" style="vertical-align: top !important;border-right: solid 1px black">
                <p style="text-align: left !important;">26.</p>
                <div>LEVEL</div>
            </td>
            <td rowspan="2" style="border-right: solid 1px black">
                NAME OF SCHOOL<br/>(Write in full)
            </td>
            <td rowspan="2" style="border-right: solid 1px black">
                BASIC EDUCATION/DEGREE/COURSE<br/>
                (Write in full)
            </td>
            <td colspan="2" style="border-right: solid 1px black">PERIOD OF ATTENDANCE</td>
            <td rowspan="2" style="font-size: 8px !important;border-right: solid 1px black">
                HIGHEST LEVEL/<br/>UNITS EARNED<br/>(if not
                graduated)
            </td>
            <td rowspan="2" style=";border-right: solid 1px black;font-size: 8px !important;">YEAR GRADUATED</td>
            <td rowspan="2" style="font-size: 8px">SCHOLARSHI<br/>P/ACADEMIC<br/>HONORS<br/>RECEIVED
            </td>
        </tr>
        <tr style="text-align: center;border-top: solid 1px  black" class="gray">
            <td style="border-right: solid 1px black">FROM</td>
            <td style="border-right: solid 1px black">TO</td>
        </tr>
        @foreach($educations as $education)
            <tr style="border-top: solid 1px  black">
                <td rowspan="{{ count($education) }}" style="width: 20%;border-right: solid 1px black"
                    class="gray">{{ $education[0]['level'] }}</td>
            @for($i=0; $i<count($education); $i++)
                @if($i > 0)
                    <tr style="border-top: solid 1px black">
                        @endif
                        <td style="width: 25%;border-right: solid 1px black;text-align: unset !important"
                            class="center-value education">
                            <div
                                style="padding-left: 3px;{{ schoolAdjustFontSize(strtoupper($education[$i]['school_name'])  ?? '', 12) }}">
                                {{ strtoupper($education[$i]['school_name']) }}
                            </div>
                        </td>
                        <td style="width: 21%;border-right: solid 1px black" class="center-value education">
                            <div
                                style="padding-left: 3px;{{ schoolAdjustFontSize(strtoupper($education[$i]['education_degree'])  ?? '', 12) }}">
                                {{ strtoupper($education[$i]['education_degree']) }}
                            </div>
                        </td>

                        <td style="width: 6%;border-right: solid 1px black"
                            class="center-value education">{{ $education[$i]['attendance_from'] }}</td>
                        <td style="width: 6%;border-right: solid 1px black"
                            class="center-value education">{{ $education[$i]['attendance_to'] }}</td>
                        <td style="width: 9%;border-right: solid 1px black"
                            class="center-value education">{{ strtoupper($education[$i]['highest_level']) }}</td>
                        <td style="width: 6%;border-right: solid 1px black"
                            class="center-value education">{{ $education[$i]['year_graduated'] }}</td>
                        <td style="width: 7%;" class="center-value education">
                            <div
                                style="{{ honorsAdjustFontSize(strtoupper($education[$i]['scholarship'])  ?? '', 8) }}">
                                {{ $education[$i]['scholarship'] }}
                            </div>
                        </td>

                        @if(count($education) > 1) </tr> @endif
                    @endfor
                    @if(count($education) < 2) </tr> @endif
                @endforeach
                <tr>
                    <td colspan="8"
                        style="border-top: solid 1px black;text-align: center;color:red;font-weight: bold;height: unset !important;"
                        class="gray">
                        (Continue on separate sheet if necessary)
                    </td>
                </tr>
                <tr style="border-top: solid 1px black;">
                    <td style="text-align: center;font-weight: bold;border-right: solid 1px black" class="gray">
                        SIGNATURE
                    </td>
                    <td colspan="2"
                        style="border-top: solid 1px black;text-align: center;color:red;font-weight: bold;height: unset !important;">

                    </td>
                    <td colspan="2" style="text-align: center;font-weight: bold;border-right: solid 1px black"
                        class="gray">
                        DATE
                    </td>
                    <td colspan="3" style="text-align: center;font-weight: bold">{{ $pds_date }}</td>
                </tr>
    </table>
</div>
<div style="text-align: right;font-size: 9.33px;font-style: italic">CS FORM 212 (Revised {{ $pds_version ?? '2026' }}), Page 1 of 4</div>
