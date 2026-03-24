<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application for Leave</title>
    <style>
        @page {
            size: A4;
            margin: 8mm;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #000;
            background: #fff;
            font-size: 11px;
        }

        .print-toolbar {
            width: 210mm;
            margin: 14px auto 0;
            display: flex;
            justify-content: flex-end;
        }

        .print-toolbar button {
            border: 1px solid #000;
            background: #fff;
            color: #000;
            padding: 8px 14px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 10px auto;
            padding: 6mm 8mm;
            background: #fff;
        }

        .top-meta {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .meta-left {
            width: 28mm;
            font-size: 9px;
            font-style: italic;
            font-weight: 700;
            line-height: 1.35;
        }

        .meta-center {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            text-align: center;
        }

        .meta-center img {
            width: 46px;
            height: 46px;
            object-fit: contain;
        }

        .meta-center-text {
            line-height: 1.35;
            font-size: 11px;
            font-weight: 700;
        }

        .meta-center-text .small {
            font-size: 10px;
            font-weight: 700;
        }

        .meta-right {
            width: 34mm;
            min-height: 18mm;
            border: 1px dashed #9ca3af;
            font-size: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 4px;
        }

        .form-title {
            text-align: center;
            font-size: 18px;
            font-weight: 700;
            margin: 2px 0 8px;
        }

        .sheet {
            border: 1px solid #000;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        td, th {
            vertical-align: top;
        }

        .master-table td {
            border: 1px solid #000;
            padding: 3px 5px;
            font-size: 10px;
        }

        .master-table .top-info-row td {
            padding-top: 0;
            padding-bottom: 0;
            vertical-align: middle;
            border-left: 0;
            border-right: 0;
        }

        .master-table .second-info-row td {
            padding-top: 0;
            padding-bottom: 0;
            vertical-align: middle;
            border-left: 0;
            border-right: 0;
        }

        .master-table .top-info-row td:first-child,
        .master-table .second-info-row td:first-child {
            border-left: 1px solid #000;
        }

        .master-table .top-info-row td:last-child,
        .master-table .second-info-row td:last-child {
            border-right: 1px solid #000;
        }

        .top-cell-box,
        .second-cell-box {
            min-height: 42px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 3px 5px;
        }

        .number-label {
            font-weight: 700;
            text-transform: uppercase;
        }

        .field-value {
            display: block;
            margin-top: 4px;
            font-size: 11px;
            font-weight: 700;
        }

        .field-line {
            display: block;
            margin-top: 2px;
            min-height: 13px;
            border-bottom: 1px solid #000;
            font-size: 11px;
            font-weight: 700;
        }

        .name-grid {
            display: grid;
            grid-template-columns: 68px repeat(3, 1fr);
            column-gap: 6px;
            row-gap: 2px;
            align-items: end;
        }

        .name-grid .number-label {
            grid-column: 1;
            grid-row: 1;
            align-self: start;
        }

        .name-head {
            font-size: 9px;
            text-align: center;
        }

        .name-head.last {
            grid-column: 2;
            grid-row: 1;
        }

        .name-head.first {
            grid-column: 3;
            grid-row: 1;
        }

        .name-head.middle {
            grid-column: 4;
            grid-row: 1;
        }

        .name-value {
            text-align: center;
        }

        .name-value.last {
            grid-column: 2;
            grid-row: 2;
        }

        .name-value.first {
            grid-column: 3;
            grid-row: 2;
        }

        .name-value.middle {
            grid-column: 4;
            grid-row: 2;
        }

        .inline-field {
            display: flex;
            align-items: center;
            gap: 6px;
            min-height: 42px;
        }

        .inline-field .field-line {
            flex: 1;
            margin-top: 0;
            text-align: center;
        }

        .inline-field .number-label {
            flex-shrink: 0;
        }

        .section-band {
            text-align: center;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            padding: 2px 0;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
        }

        .detail-shell {
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
        }

        .detail-shell td {
            border-right: 1px solid #000;
            padding: 4px 6px 5px;
            font-size: 10px;
        }

        .detail-shell td:last-child {
            border-right: 0;
        }

        .detail-shell .top-detail-row > td {
            height: 285px;
        }

        .detail-shell .bottom-detail-row > td {
            border-top: 1px solid #000;
            padding-top: 6px;
            padding-bottom: 6px;
        }

        .subsection-title {
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .option-row {
            display: flex;
            align-items: flex-start;
            gap: 5px;
            line-height: 1.25;
            margin-bottom: 4px;
        }

        .check {
            width: 11px;
            height: 11px;
            border: 1px solid #000;
            flex-shrink: 0;
            margin-top: 1px;
            text-align: center;
            font-size: 9px;
            line-height: 10px;
            font-weight: 700;
        }

        .option-copy {
            flex: 1;
        }

        .option-copy small {
            font-size: 7px;
        }

        .guide-line {
            display: inline-block;
            min-width: 110px;
            border-bottom: 1px solid #000;
            padding-bottom: 1px;
            font-weight: 700;
        }

        .detail-guide {
            display: inline-block;
            min-width: 150px;
            border-bottom: 1px solid #000;
            padding-bottom: 1px;
            font-weight: 700;
        }

        .detail-note {
            font-style: italic;
            font-weight: 700;
            margin: 7px 0 5px;
        }

        .detail-shell .option-row {
            margin-bottom: 5px;
        }

        .section-value {
            font-weight: 700;
            font-size: 11px;
            margin: 4px 0 2px 18px;
        }

        .applicant-line {
            margin-top: 14px;
            text-align: center;
            font-size: 10px;
        }

        .applicant-line .sign-line {
            display: block;
            border-top: 1px solid #000;
            width: 72%;
            margin: 18px auto 0;
            padding-top: 2px;
        }

        .action-table {
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
        }

        .action-table > tbody > tr > td {
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 6px;
            font-size: 10px;
        }

        .action-table > tbody > tr:last-child > td {
            border-bottom: 0;
        }

        .action-table > tbody > tr > td:last-child {
            border-right: 0;
        }

        .action-table > tbody > tr.no-bottom-border > td {
            border-bottom: 0;
        }

        .action-table > tbody > tr.no-middle-separator > td:first-child {
            border-right: 0;
        }

        .mini-table {
            width: 78%;
            margin: 18px auto 8px;
            border-collapse: collapse;
            font-size: 9px;
        }

        .mini-table td,
        .mini-table th {
            border: 1px solid #000;
            padding: 2px 4px;
            text-align: center;
        }

        .mini-table .italic {
            font-style: italic;
            font-weight: 700;
            text-align: left;
        }

        .action-title {
            font-size: 10px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .as-of-line {
            text-align: center;
            font-size: 10px;
            margin-top: 8px;
        }

        .as-of-line .line-fill {
            min-width: 130px;
        }

        .sign-block {
            text-align: center;
            margin-top: 26px;
            font-size: 9px;
        }

        .sign-block .name {
            display: inline-block;
            min-width: 180px;
            border-bottom: 1px solid #000;
            padding: 0 8px 2px;
            font-weight: 700;
        }

        .line-fill {
            display: inline-block;
            min-width: 180px;
            border-bottom: 1px solid #000;
            height: 12px;
            vertical-align: bottom;
        }

        .indent {
            margin-left: 18px;
        }

        .recommendation-stack {
            margin: 6px 0 0 22px;
        }

        .recommendation-stack .guide-line {
            display: block;
            width: 84%;
            min-width: 0;
            margin: 8px 0 0;
        }

        .signature-line {
            width: 88%;
            margin: 24px auto 0;
            border-top: 1px solid #000;
            text-align: center;
            padding-top: 2px;
            font-size: 9px;
        }

        .signature-line .name-inline {
            display: inline-block;
            background: #fff;
            margin-top: -12px;
            padding: 0 8px;
            font-weight: 700;
        }

        .signature-line .caption {
            display: block;
            margin-top: 10px;
        }

        .approved-lines {
            margin: 8px 0 0 18px;
            font-size: 10px;
        }

        .approved-lines div {
            margin-bottom: 4px;
        }

        .approved-lines .line-fill {
            min-width: 68px;
            margin-right: 4px;
        }

        .disapproved-lines {
            margin: 12px 0 0 20px;
        }

        .disapproved-lines .guide-line {
            display: block;
            width: 78%;
            min-width: 0;
            margin-bottom: 10px;
        }

        .bottom-authority {
            text-align: center;
            padding: 0 0 8px;
            margin-top: 15px;
        }

        .bottom-authority .name-inline {
            display: inline-block;
            font-weight: 700;
            font-size: 11px;
            padding: 0 8px;
            background: #fff;
        }

        .bottom-authority .line {
            width: 34%;
            margin: 8px auto 2px;
            border-top: 1px solid #000;
        }

        .bottom-authority .caption {
            font-size: 9px;
            font-weight: 700;
        }

        @media print {
            .print-toolbar {
                display: none;
            }

            .page {
                margin: 0;
                padding: 4mm 6mm;
            }
        }
    </style>
</head>
<body>
<div class="print-toolbar">
    <button type="button" onclick="window.print()">Print</button>
</div>

<div class="page">
    <div class="top-meta">
        <div class="meta-left">
            <div>Civil Service Form No. 6</div>
            <div>Revised 2020</div>
        </div>
        <div class="meta-center">
            <img src="{{ asset('assets/img/logo.png') }}" alt="Logo">
            <div class="meta-center-text">
                <div>Republic of the Philippines</div>
                <div>CEBU SOUTH MEDICAL CENTER</div>
                <div class="small">San Isidro, Talisay City, Cebu</div>
            </div>
        </div>
        <div class="meta-right">Stamp of Date of Receipt</div>
    </div>

    <div class="form-title">APPLICATION FOR LEAVE</div>

    <div class="sheet">
        <table class="master-table">
            <colgroup>
                <col style="width: 32%;">
                <col style="width: 22.6667%;">
                <col style="width: 22.6667%;">
                <col style="width: 22.6666%;">
            </colgroup>
            <tr class="top-info-row">
                <td>
                    <div class="top-cell-box">
                        <span class="number-label">1. OFFICE/DEPARTMENT</span>
                        <span class="field-value">{{ $officeLabel }}</span>
                    </div>
                </td>
                <td colspan="3">
                    <div class="top-cell-box">
                        <div class="name-grid">
                            <span class="number-label">2. NAME :</span>
                            <span class="name-head last">(Last)</span>
                            <span class="name-head first">(First)</span>
                            <span class="name-head middle">(Middle)</span>

                            <span class="field-line name-value last">{{ strtoupper($leaveApplication->lname) }}</span>
                            <span class="field-line name-value first">{{ strtoupper($leaveApplication->fname) }}</span>
                            <span class="field-line name-value middle">{{ $leaveApplication->mname ? strtoupper(substr($leaveApplication->mname, 0, 1)) . '.' : '' }}</span>
                        </div>
                    </div>
                </td>
            </tr>
            <tr class="second-info-row">
                <td>
                    <div class="second-cell-box">
                        <div class="inline-field">
                            <span class="number-label">3. DATE OF FILING</span>
                            <span class="field-line">{{ $formattedCreatedAt }}</span>
                        </div>
                    </div>
                </td>
                <td colspan="2">
                    <div class="second-cell-box">
                        <div class="inline-field">
                            <span class="number-label">4. POSITION</span>
                            <span class="field-line">{{ $designationLabel }}</span>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="second-cell-box">
                        <div class="inline-field">
                            <span class="number-label">5. SALARY</span>
                            <span class="field-line">{{ $salaryLabel }}</span>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="4" class="section-band">6. DETAILS OF APPLICATION</td>
            </tr>
        </table>

        <table class="detail-shell">
            <tr class="top-detail-row">
                <td style="width: 50%;">
                    <div class="subsection-title">6.A TYPE OF LEAVE TO BE AVAILED OF</div>
                    @foreach($leaveTypeOptions as $option)
                        <div class="option-row">
                            <span class="check">{{ (int) $leaveApplication->leave_id === (int) $option['id'] ? 'X' : '' }}</span>
                            <span class="option-copy">
                                {{ $option['label'] }}
                                @if($option['id'] === 2)
                                    <small>(Sec. 51, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</small>
                                @elseif($option['id'] === 8)
                                    <small>(Sec. 25, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</small>
                                @elseif($option['id'] === 1)
                                    <small>(Sec. 43, Rule XV, Omnibus Rules Implementing E.O. No. 292)</small>
                                @elseif($option['id'] === 9)
                                    <small>(R.A. No. 11210 / IRR issued by CSC, DOLE and SSS)</small>
                                @elseif($option['id'] === 3)
                                    <small>(R.A. No. 8187 / CSC MC No. 71, s. 1998, as amended)</small>
                                @elseif($option['id'] === 10)
                                    <small>(Sec. 21, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</small>
                                @elseif($option['id'] === 4)
                                    <small>(R.A. 8972 / CSC MC No. 8, s. 2004)</small>
                                @elseif($option['id'] === 11)
                                    <small>(Sec. 68, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</small>
                                @elseif($option['id'] === 5)
                                    <small>(R.A. No. 9262 / CSC MC No. 15, s. 2005)</small>
                                @elseif($option['id'] === 12)
                                    <small>(Sec. 56, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</small>
                                @elseif($option['id'] === 6)
                                    <small>(R.A. No. 9710 / CSC MC No. 25, s. 2010)</small>
                                @elseif($option['id'] === 13)
                                    <small>(CSC MC No. 2, s. 2012, as amended)</small>
                                @elseif($option['id'] === 7)
                                    <small>(R.A. No. 8552)</small>
                                @endif
                            </span>
                        </div>
                    @endforeach

                    <div class="detail-note">Others:</div>
                    <span class="detail-guide">{{ $otherLeaveType ?: '' }}</span>
                </td>
                <td style="width: 50%;">
                    <div class="subsection-title">6.B DETAILS OF LEAVE</div>

                    <div class="detail-note">In case of Vacation/Special Privilege Leave:</div>
                    <div class="option-row">
                        <span class="check">{{ $leaveApplication->detail === 'Within the Philippines' ? 'X' : '' }}</span>
                        <span class="option-copy">Within the Philippines <span class="detail-guide">{{ $leaveApplication->detail === 'Within the Philippines' ? $leaveApplication->extra : '' }}</span></span>
                    </div>
                    <div class="option-row">
                        <span class="check">{{ $leaveApplication->detail === 'Abroad (Specify)' ? 'X' : '' }}</span>
                        <span class="option-copy">Abroad (Specify) <span class="detail-guide">{{ $leaveApplication->detail === 'Abroad (Specify)' ? $leaveApplication->extra : '' }}</span></span>
                    </div>

                    <div class="detail-note" style="margin-top: 8px;">In case of Sick Leave:</div>
                    <div class="option-row">
                        <span class="check">{{ $leaveApplication->detail === 'In Hospital (Specify Illness)' ? 'X' : '' }}</span>
                        <span class="option-copy">In Hospital (Specify Illness) <span class="detail-guide">{{ $leaveApplication->detail === 'In Hospital (Specify Illness)' ? $leaveApplication->extra : '' }}</span></span>
                    </div>
                    <div class="option-row">
                        <span class="check">{{ $leaveApplication->detail === 'Out Patient (Specify Illness)' ? 'X' : '' }}</span>
                        <span class="option-copy">Out Patient (Specify Illness) <span class="detail-guide">{{ $leaveApplication->detail === 'Out Patient (Specify Illness)' ? $leaveApplication->extra : '' }}</span></span>
                    </div>

                    <div class="detail-note" style="margin-top: 8px;">In case of Special Leave Benefits for Women:</div>
                    <div class="indent">(Specify Illness) <span class="detail-guide">{{ $leaveApplication->detail === 'In case of Special Leave Benefits for Women' ? $leaveApplication->extra : '' }}</span></div>

                    <div class="detail-note" style="margin-top: 8px;">In case of Study Leave:</div>
                    <div class="option-row">
                        <span class="check">{{ $leaveApplication->detail === "Completion of Master's Degree" ? 'X' : '' }}</span>
                        <span class="option-copy">Completion of Master's Degree</span>
                    </div>
                    <div class="option-row">
                        <span class="check">{{ $leaveApplication->detail === 'BAR/Board Examination Review' ? 'X' : '' }}</span>
                        <span class="option-copy">BAR/Board Examination Review</span>
                    </div>

                    <div class="detail-note" style="margin-top: 8px;">Other purpose:</div>
                    <div class="option-row">
                        <span class="check">{{ $leaveApplication->detail === 'Monetization of Leave Credits' ? 'X' : '' }}</span>
                        <span class="option-copy">Monetization of Leave Credits</span>
                    </div>
                    <div class="option-row">
                        <span class="check">{{ $leaveApplication->detail === 'Terminal Leave' ? 'X' : '' }}</span>
                        <span class="option-copy">Terminal Leave</span>
                    </div>
                </td>
            </tr>
            <tr class="bottom-detail-row">
                <td>
                    <div class="subsection-title">6.C NUMBER OF WORKING DAYS APPLIED FOR</div>
                    <div class="section-value">{{ $totalDays }} day(s) ({{ $totalHours }} hrs)</div>
                    <span class="detail-guide" style="margin-left: 18px; min-width: 220px;"></span>
                    <div class="detail-note" style="margin-top: 6px; margin-left: 18px;">INCLUSIVE DATES</div>
                    <div class="section-value" style="margin-top: 0;">{{ $inclusiveDates }}</div>
                    <span class="detail-guide" style="margin-left: 18px; min-width: 230px;"></span>
                </td>
                <td>
                    <div class="subsection-title">6.D COMMUTATION</div>
                    <div class="option-row">
                        <span class="check">{{ $leaveApplication->commutation === 'Not Requested' ? 'X' : '' }}</span>
                        <span class="option-copy">Not Requested</span>
                    </div>
                    <div class="option-row">
                        <span class="check">{{ $leaveApplication->commutation === 'Requested' ? 'X' : '' }}</span>
                        <span class="option-copy">Requested</span>
                    </div>
                    <div class="applicant-line">
                        <span class="sign-line">(Signature of Applicant)</span>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="section-band">7. DETAILS OF ACTION ON APPLICATION</td>
            </tr>
        </table>

        <table class="action-table">
            <tr>
                <td style="width: 50%;">
                    <div class="action-title">7.A&nbsp; CERTIFICATION OF LEAVE CREDITS</div>
                    <div class="as-of-line">As <span class="line-fill"></span></div>

                    <table class="mini-table">
                        <tr>
                            <th style="width: 42%;"></th>
                            <th>Vacation Leave</th>
                            <th>Sick Leave</th>
                        </tr>
                        <tr>
                            <td class="italic">Total Earned</td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td class="italic">Less this application</td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td class="italic">Balance</td>
                            <td></td>
                            <td></td>
                        </tr>
                    </table>

                    <div class="signature-line">
                        <span class="caption">(Authorized Officer)</span>
                    </div>
                </td>
                <td style="width: 50%;">
                    <div class="action-title">7.B&nbsp; RECOMMENDATION</div>
                    <div class="option-row" style="margin-top: 6px;">
                        <span class="check"></span>
                        <span class="option-copy">For approval</span>
                    </div>
                    <div class="option-row">
                        <span class="check"></span>
                        <span class="option-copy">For disapproval due to <span class="guide-line" style="min-width: 170px;"></span></span>
                    </div>
                    <div class="recommendation-stack">
                        <span class="guide-line"></span>
                        <span class="guide-line"></span>
                        <span class="guide-line"></span>
                    </div>

                    <div class="signature-line" style="margin-top: 22px;">
                        <span class="name-inline">{{ $authorizedOfficerName }}</span>
                        <span class="caption">(Authorized Officer)</span>
                    </div>
                </td>
            </tr>
            <tr class="no-bottom-border no-middle-separator">
                <td>
                    <div class="action-title">7.C&nbsp; APPROVED FOR:</div>
                    <div class="approved-lines">
                        <div><span class="line-fill"></span>days with pay</div>
                        <div><span class="line-fill"></span>days without pay</div>
                        <div><span class="line-fill"></span>others (Specify)</div>
                    </div>
                </td>
                <td>
                    <div class="action-title">7.D&nbsp; DISAPPROVED DUE TO:</div>
                    <div class="disapproved-lines">
                        <span class="guide-line"></span>
                        <span class="guide-line"></span>
                        <span class="guide-line"></span>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div class="bottom-authority">
                        <span class="name-inline">{{ $authorizedOfficialName }}</span>
                        <div class="line"></div>
                        <div class="caption">(Authorized Official)</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>
