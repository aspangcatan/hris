@include('pdf.service_records_header')
<table class="border-dark" style="margin-top:15px">
    <tr class="text-v-center text-center">
        <td class="b-b b-r" colspan="2" style="width: 15%">Services inclusive</br>Dates</td>
        <td class="b-b b-r" rowspan="2" style="width: 15%">RECORD OF <br/>APPOINTMENT<br/>DESIGNATION</td>
        <td class="b-b b-r" rowspan="2" style="width: 10%">STATUS</td>
        <td class="b-b b-r" rowspan="2" style="width: 18%">SALARY<br/>(ANNUM)</td>
        <td class="b-b b-r" rowspan="2" style="width: 18%">OFFICE OF ASSIGNMENT</td>
        <td class="b-b b-r" rowspan="2" style="width: 10%">LEAVE OF<br/>ABSENCES<br/>W/O PAY</td>
        <td class="b-b" rowspan="2" style="width: 12%">SEPARATION</td>
    </tr>
    <tr class="text-v-center text-center">
        <td class="b-b b-r">From</td>
        <td class="b-b b-r">To</td>
    </tr>
    @foreach($service_records as $data)
        <tr class="text-center text-v-center">
            <td class="b-b b-r">{{ date('m/d/Y', strtotime($data['date_from'])) }}</td>
            <td class="b-b b-r">{{ $data['current_work'] == 1 ? 'Present' : date('m/d/Y', strtotime($data['date_to'])) }}</td>
            <td class="b-b b-r">{{ $data['designation'] }}</td>
            <td class="b-b b-r">{{ $data['appointment_status'] }}</td>
            <td class="b-b b-r">{{ number_format($data['monthly_salary'],2) }}</td>
            <td class="b-b b-r">{{ $data['office_assignment'] }}</td>
            <td class="b-b b-r">{!! nl2br(e($data['leave_absence'])) !!}</td>
            <td class="b-b">{{ $data['separation'] }}</td>
        </tr>
    @endforeach
</table>
@include('pdf.service_records_footer')
