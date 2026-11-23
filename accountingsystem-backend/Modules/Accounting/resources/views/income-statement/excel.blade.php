{{-- Company Name and Date Text --}}
<table>
    <tbody>
        <tr >
            <td colspan="{{ count($labels) + 1 }}" style="font-weight: bold;">{{$companyName}}</td>
        </tr>
        <tr>
            <td colspan="{{ count($labels) + 1 }}">Income Statement Report</td>
        </tr>
        <tr>
            <td colspan="{{ count($labels) + 1 }}">{{"For the period of $labels[0]"}}</td>
        </tr>
        <tr>
            <td colspan="{{ count($labels) + 1 }}" style="font-style: italic">Amounts in Philippine Peso (₱)</td>
        </tr>
    </tbody>
</table>

{{-- Revenue Table --}}
<table>
    <thead>
        <tr>
            <th colspan="{{ count($labels) + 1 }}" style="font-weight: bold; border-bottom: 2px solid black">
                REVENUE
            </th>
        </tr>
        <tr>
            <th style="font-weight: bold; border-bottom: 2px solid black">Account Name</th>
            @foreach($labels as $dateLabel)
                <th style="font-weight: bold; border-bottom: 2px solid black; text-align: right">
                    {{ $dateLabel }}
                </th>
            @endforeach
        </tr>
    </thead>

    <tbody>
    @foreach($revenueAccounts as $account)
        <tr>
            <td>{{$account['name']}}</td>
            <td data-format='#,##0.00;(#,##0.00)' style="text-align: right;">
                {{ $account['balance'] }}
            </td>
            @if(isset($account['previousBalances']))
                @foreach($account['previousBalances'] as $previousBalance)
                    <td data-format='#,##0.00;(#,##0.00)' style="text-align: right;">
                        {{ $previousBalance }}
                    </td>
                @endforeach
            @endif
        </tr>
    @endforeach
    </tbody>

    <tfoot>
        <tr>
            <td style="font-weight: bold; border-top: 2px solid black">TOTAL REVENUE</td>
            @foreach($totalRevenue as $revenue)
                <td data-format='#,##0.00;(#,##0.00)' style="text-align: right; font-weight: bold; border-top: 2px solid black">
                    {{ $revenue }}
                </td>
            @endforeach
        </tr>
    </tfoot>
</table>

{{-- Expense Table --}}
<table>
    <thead>
        <tr>
            <th colspan="{{ count($labels) + 1 }}" style="font-weight: bold; border-bottom: 2px solid black">
                EXPENSES
            </th>
        </tr>
        <tr>
            <th style="font-weight: bold; border-bottom: 2px solid black">Account Name</th>
            @foreach($labels as $dateLabel)
                <th style="font-weight: bold; border-bottom: 2px solid black; text-align: right">
                    {{ $dateLabel }}
                </th>
            @endforeach
        </tr>
    </thead>

    <tbody>
    @foreach($expenseAccounts as $account)
        <tr>
            <td>{{$account['name']}}</td>
            <td data-format='#,##0.00;(#,##0.00)' style="text-align: right;">
                {{ $account['balance'] }}
            </td>
            @if(isset($account['previousBalances']))
                @foreach($account['previousBalances'] as $previousBalance)
                    <td data-format='#,##0.00;(#,##0.00)' style="text-align: right;">
                        {{ $previousBalance }}
                    </td>
                @endforeach
            @endif
        </tr>
    @endforeach
    </tbody>

    <tfoot>
        <tr>
            <td style="font-weight: bold; border-top: 2px solid black">TOTAL EXPENSES</td>
            @foreach($totalExpense as $expense)
                <td data-format='#,##0.00;(#,##0.00)' style="text-align: right; font-weight: bold; border-top: 2px solid black">
                    {{ $expense }}
                </td>
            @endforeach
        </tr>
        <tr>
            <td style="font-weight: bold; border-top: 2px solid black">NET PROFIT/LOSS</td>
            @foreach($netTotal as $total)
                <td data-format='#,##0.00;(#,##0.00)' style="text-align: right; font-weight: bold; border-top: 2px solid black">
                    {{ $total }}
                </td>
            @endforeach
        </tr>
    </tfoot>
</table>
