<div class="text-center fw-bold title" style="padding-top:{{ $header_padding_top ?? 122 }}pt">
    <span class="b-b">SERVICE RECORD</span>
</div>
<div style="margin-left: 10px;margin-right: 10px;margin-top:20px">
    <table class="text-center">
        <tr>
            <td class="text-left" style="width: 9%">
                <div>NAME:</div>
            </td>
            <td style="width: 21%">
                <div class="text-name">{{ strtoupper($user->lname) }}</div>
                <div>(SURNAME)</div>
            </td>
            <td style="width: 30%">
                <div class="text-name">{{ strtoupper($user->fname) }}</div>
                <div>(GIVEN NAME)</div>
            </td>
            <td style="width: 20%">
                <div class="text-name">{{ strtoupper($user->mname) }}</div>
                <div>(MIDDLE NAME)</div>
            </td>
            <td style="width: 20%"></td>
        </tr>
    </table>
    <table class="text-left">
        <tr>
            <td style="width: 9%">
                <div class="mt-1">BIRTHDATE:</div>
            </td>
            <td style="width: 21%">
                <div class="mt-1" style="padding-left: 3px">{{ date('F j, Y', strtotime($employee->birthdate)) }}</div>
            </td>
            <td style="width: 13%">
                <div class="mt-1">PLACE OF BIRTH:</div>
            </td>
            <td style="width: 53%">
                <div class="mt-1">{{ strtoupper($employee->birthplace) }}</div>
            </td>
        </tr>
    </table>
    <div style="margin-top: 40px;margin-bottom:10px">
        THIS IS TO CERTIFY that the employee herein actually rendered services in this Office as shown by the Service
        Record below, each line which is supporting appointment and other papers actually issued by this Office and approved by the authorities concerned.
    </div>
</div>
