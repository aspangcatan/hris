<div id="page_5" style="border:1px solid black">
    <table style="border-top: solid 1px black">
        <tr style="background: gray">
            <td colspan="8"
                class="title">VII. LEARNING AND DEVELOPMENT (L&D) INTERVENTIONS/TRAINING PROGRAMS ATTENDED
            </td>
        </tr>
        <tr style="text-align: center" class="gray">
            <td rowspan="2" style="vertical-align: top !important;border-right: solid 1px black">
                <div>30. TITLE OF LEARNING AND DEVELOPMENT INTERVENTIONS/TRAINING PROGRAMS<br/>
                    (Write in full)
                </div>
            </td>
            <td colspan="2" style="border-right: solid 1px black">
                INCLUSIVE DATES OF<br/>
                ATTENDANCE<br/>
                (mm/dd/yyyy)
            </td>
            <td rowspan="2" style="border-right: solid 1px black">
                NUMBER OF HOURS
            </td>
            <td rowspan="2" style=";border-right: solid 1px black">Type of LD<br/>
                (Managerial/<br/>Supervisory/<br/>Technical/etc)
            </td>
            <td rowspan="2">CONDUCTED/SPONSORED BY<br/>(Write in full)
            </td>
        </tr>
        <tr style="text-align: center;border-top: solid 1px  black" class="gray">
            <td style=";border-right: solid 1px black">From</td>
            <td style="border-right: solid 1px black">To</td>
        </tr>
        @foreach($learning as $key)
            <tr style="border-top: solid 1px  black">
                <td style="width: 45%;border-right: solid 1px black" class="center-value">{{ strtoupper($key['learning_title']) }}</td>
                <td style="width: 7%;border-right: solid 1px black" class="center-value">{{ strtoupper($key['attendance_from']) }}</td>
                <td style="width: 7%;border-right: solid 1px black" class="center-value">{{ strtoupper($key['attendance_to']) }}</td>
                <td style="width: 7%;border-right: solid 1px black" class="center-value">{{ strtoupper($key['number_hours']) }}</td>
                <td style="width: 7%;border-right: solid 1px black" class="center-value">{{ strtoupper($key['learning_type']) }}</td>
                <td style="width: 27%;" class="center-value">{{ strtoupper($key['conducted_by']) }}</td>
            </tr>
        @endforeach
        <tr style="border-top: solid 1px  black;text-align: center">
            <td colspan="6" style="color:red;font-weight: bold;height:  unset !important;" class="gray">(Continue on
                separate sheet if necessary)
            </td>
        </tr>
    </table>
</div>
