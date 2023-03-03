<div class="border-lg" style="margin-top:10px">
    <div class="item pl-1 border-dark">
        <div>VII. LEARNING AND DEVELOPMENT (L&D) INTERVENTIONS/TRAINING PROGRAMS ATTENDED</div>
        <small>(Start from the most recent L&D/training program and include only the relevant L&D/training for the last
            five (5) years of Division Chief/Executive/Managerial position)</small></div>
    <table class="w-100 h-100 no-spacing border" border="0">
        <tr class="h-100 bg-gray-white" style="font-size: 9px !important;">
            <td class="border-dark v-center text-center" rowspan="2" style="width: 40%">
                30. TITLE OF LEARNING AND DEVELOPMENT INTERVENTIONS/TRAINING/PROGRAMS<br/>(Write in full)

            </td>
            <td class="border-dark v-center text-center" colspan="2">
                INCLUSIVE DATES<br/>(mm/dd/yyyy)
            </td>
            <td class="border-dark v-center text-center" rowspan="2">
                NUMBER OF HOURS
            </td>
            <td class="border-dark v-center text-center" rowspan="2">
                Type of LD<br/>(Managerial/<br/>Supervisory/<br/>Technical/etc)
            </td>
            <td class="border-dark v-center text-center" rowspan="2">
                CONDUCTED/SPONSORED BY<br/>(Write in full)
            </td>
        </tr>
        <tr class="bg-gray-white">
            <td class="border-dark v-center text-center">
                FROM
            </td>
            <td class="border-dark v-center text-center">
                TO
            </td>
        </tr>
        @foreach($learning as $key)
            <tr>
                <td style="height: 33px !important;" class="border-dark v-center text-center fw-bold"> {{ strtoupper($key['learning_title']) }}</td>
                <td class="border-dark v-center text-center fw-bold"> {{ strtoupper($key['attendance_from']) }}</td>
                <td class="border-dark v-center text-center fw-bold"> {{ strtoupper($key['attendance_to']) }}</td>
                <td class="border-dark v-center text-center fw-bold"> {{ strtoupper($key['number_hours']) }}</td>
                <td class="border-dark v-center text-center fw-bold"> {{ strtoupper($key['learning_type']) }}</td>
                <td class="border-dark v-center text-center fw-bold"> {{ strtoupper($key['conducted_by']) }}</td>
            </tr>
        @endforeach
        <tr class="bg-gray-white">
            <td colspan="6" style="color: darkred;height: unset !important;" class="v-center text-center fw-bold">
                (Continue on separate sheet if necessary)
            </td>
        </tr>
    </table>
</div>
