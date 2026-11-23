{{-- Company Name and Date Text --}}
<table>
    <tbody>
    <tr >
        <td colspan="3" style="font-weight: bold;">{{$companyName}}</td>
    </tr>
    <tr>
        <td colspan="3">Trial Balance Report</td>
    </tr>
    <tr>
        <td colspan="3">{{$dateText}}</td>
    </tr>
    <tr>
        <td colspan="3" style="font-style: italic">Amounts in Philippine Peso (₱)</td>
    </tr>
    </tbody>
</table>

<table>
    <thead>
        <tr>
            <th style="font-weight: bold; text-align: left; border-top: 2px solid black; border-bottom: 2px solid black">ACCOUNT</th>
            <th style="font-weight: bold; text-align: right; border-top: 2px solid black; border-bottom: 2px solid black">NET DEBIT (Dr)</th>
            <th style="font-weight: bold; text-align: right; border-top: 2px solid black; border-bottom: 2px solid black">NET CREDIT (Cr)</th>
        </tr>
    </thead>

    <tbody>
        <tr>
            <td colspan="3" style="font-weight: bold; border-bottom: 2px solid black">Asset</td>
        </tr>
        @foreach($assetAccounts as $account)
            <tr>
                <td style="text-indent: 2px">{{$account['name']}}</td>
                <td data-format='#,##0.00;(#,##0.00)' style="text-align: right;">
                    {{ $account['debit'] > 0 ? $account['debit'] : '' }}
                </td>
                <td data-format='#,##0.00;(#,##0.00)' style="text-align: right;">
                    {{ $account['credit'] > 0 ? $account['credit'] : '' }}
                </td>
            </tr>
        @endforeach

        <tr>
            <td colspan="3" style="font-weight: bold; border-bottom: 2px solid black; border-top: 2px solid black">Liability</td>
        </tr>
        @foreach($liabilityAccounts as $account)
            <tr>
                <td style="text-indent: 2px">{{$account['name']}}</td>
                <td data-format='#,##0.00;(#,##0.00)' style="text-align: right;">
                    {{ $account['debit'] > 0 ? $account['debit'] : '' }}
                </td>
                <td data-format='#,##0.00;(#,##0.00)' style="text-align: right;">
                    {{ $account['credit'] > 0 ? $account['credit'] : '' }}
                </td>
            </tr>
        @endforeach

        <tr>
            <td colspan="3" style="font-weight: bold; border-bottom: 2px solid black; border-top: 2px solid black">Equity</td>
        </tr>
        @foreach($equityAccounts as $account)
            <tr>
                <td style="text-indent: 2px">{{$account['name']}}</td>
                <td data-format='#,##0.00;(#,##0.00)' style="text-align: right;">
                    {{ $account['debit'] > 0 ? $account['debit'] : '' }}
                </td>
                <td data-format='#,##0.00;(#,##0.00)' style="text-align: right;">
                    {{ $account['credit'] > 0 ? $account['credit'] : '' }}
                </td>
            </tr>
        @endforeach

        <tr>
            <td colspan="3" style="font-weight: bold; border-bottom: 2px solid black; border-top: 2px solid black">Revenue</td>
        </tr>
        @foreach($revenueAccounts as $account)
            <tr>
                <td style="text-indent: 2px">{{$account['name']}}</td>
                <td data-format='#,##0.00;(#,##0.00)' style="text-align: right;">
                    {{ $account['debit'] > 0 ? $account['debit'] : '' }}
                </td>
                <td data-format='#,##0.00;(#,##0.00)' style="text-align: right;">
                    {{ $account['credit'] > 0 ? $account['credit'] : '' }}
                </td>
            </tr>
        @endforeach

        <tr>
            <td colspan="3" style="font-weight: bold; border-bottom: 2px solid black; border-top: 2px solid black">Expense</td>
        </tr>
        @foreach($expenseAccounts as $account)
            <tr>
                <td style="text-indent: 2px">{{$account['name']}}</td>
                <td data-format='#,##0.00;(#,##0.00)' style="text-align: right;">
                    {{ $account['debit'] > 0 ? $account['debit'] : '' }}
                </td>
                <td data-format='#,##0.00;(#,##0.00)' style="text-align: right;">
                    {{ $account['credit'] > 0 ? $account['credit'] : '' }}
                </td>
            </tr>
        @endforeach
    </tbody>

    <tfoot>
        <tr>
            <td style="font-weight: bold; text-align: left; border-bottom: 2px solid black; border-top: 2px solid black">TOTAL</td>
            <td data-format='#,##0.00;(#,##0.00)' style="font-weight: bold; text-align: right; border-bottom: 2px solid black; border-top: 2px solid black">
                {{ $totalDebit }}
            </td>
            <td data-format='#,##0.00;(#,##0.00)' style="font-weight: bold; text-align: right; border-bottom: 2px solid black; border-top: 2px solid black">
                {{ $totalCredit }}
            </td>
        </tr>
    </tfoot>
</table>
