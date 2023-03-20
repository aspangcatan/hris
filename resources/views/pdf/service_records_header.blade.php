<img src="img/letter_head_banner.png"/>
<div class="text-center fw-bold title" style="margin-top:-40px">
    <span class="b-b">SERVICE RECORD</span>
</div>
<div style="margin-left: 10px;margin-right: 10px">
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
        <tr>
            <td class="text-left">
                <div class="mt-1">BIRTHDATE:</div>
            </td>
            <td>
                <div class="mt-1">{{ date('F j, Y', strtotime($employee->birthdate)) }}</div>
            </td>
            <td class="text-left">
                <div class="mt-1">
                    PLACE OF BIRTH:
                    <span style="margin-left: 20px">{{ strtoupper($employee->birthplace) }}</span>
                </div>
            </td>
            <td colspan="2"></td>
        </tr>
    </table>
    <div style="margin-top: 40px">
        THIS IS TO CERTIFY that the employee herein actually rendered services in this Office as shown by the Service
        Record below, each line which is supporting<br/>
        appointment and other papers actually issued by this Office and approved by the authorities concerned.
    </div>
</div>
