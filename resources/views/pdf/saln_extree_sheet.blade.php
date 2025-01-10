<div class="fw-bold" style="margin-top: 20px;margin-left: 30px;font-size: 14px">
    b. Personal Properties*
</div>
<table style="text-align: center;margin-top: 10px;font-size: 12px">
    <tr class="fw-bold gray border-top border-left">
        <td class="border-right">DESCRIPTION</td>
        <td class="border-right border-top">YEAR ACQUIRED</td>
        <td class="border-top border-right">AQUISITION<br/>COST/AMOUNT</td>
    </tr>
    @foreach($personal_properties_extended as $key)
        <tr class="border-top border-right border-left" style="font-size: 11px">
            <td class="fw-bold border-right" style="width: 50%">{{ strtoupper($key['gadget']) }}</td>
            <td class="fw-bold border-right" style="width: 36%">{{ strtoupper($key['various_years']) }}</td>
            <td class="fw-bold" style="width: 14%">{{ strtoupper($key['acquisition_cost']) }}</td>
        </tr>
    @endforeach
    <tr class="border-top" style="font-size: 13.33px">
        <td colspan="2" style="text-align: right;" class="fw-bold">Subtotal:</td>
        <td style="text-align: left">
            <div class="fw-bold border-bottom" style="padding-left:10px;">
                {{ $sub_total }}
            </div>
        </td>
    </tr>
    <tr>
        <td colspan="3" style="color: white">N/A</td>
    </tr>
    <tr style="font-size: 14px">
        <td colspan="2" style="text-align: right;" class="fw-bold">TOTAL ASSETS (a+b):</td>
        <td style="text-align: left">
            <div class="fw-bold border-bottom" style="padding-left:10px;border-bottom: solid 3px black">
                {{ $total_assets   }}
            </div>
        </td>
    </tr>
</table>
<div
    style="position: absolute; bottom: 10px; text-align: center; font-size: 16px; color: gray; font-style: italic; font-family: 'Times New Roman'; width: 100%;">
    Page 2 of 3
</div>

