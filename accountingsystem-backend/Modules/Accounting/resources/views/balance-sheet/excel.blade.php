{{-- Company Name and Date Text --}}
<table>
    <tbody>
        <tr >
            <td colspan="{{ count($labels) + 1 }}" style="font-weight: bold;">{{$companyName}}</td>
        </tr>
        <tr>
            <td colspan="{{ count($labels) + 1 }}">Balance Sheet Report</td>
        </tr>
        <tr>
            <td colspan="{{ count($labels) + 1 }}">{{$dateText}}</td>
        </tr>
        <tr>
            <td colspan="{{ count($labels) + 1 }}" style="font-style: italic">Amounts in Philippine Peso (₱)</td>
        </tr>
    </tbody>
</table>

{{-- Assets Table --}}
<table>
    <thead>
        <tr>
            <th style="font-weight: bold; text-align: left">
                ASSETS
            </th>
            @foreach($labels as $dateLabel)
                <th style="font-weight: bold; text-align: right">
                    {{ $dateLabel }}
                </th>
            @endforeach
        </tr>
    </thead>

    <tbody>
        {{-- Current Assets --}}
        <tr>
            <td colspan="{{ count($labels) + 1 }}" style="font-weight: bold; border-bottom: 1px solid black">Current Assets</td>
        </tr>
        @foreach($currentAssets as $account)
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
        <tr>
            <td style="font-weight: bold; border-top: 1px solid black">Total Current Assets</td>
            @foreach($totalCurrentAssets as $currentAsset)
                <td data-format='#,##0.00;(#,##0.00)' style="font-weight: bold; text-align: right; border-top: 1px solid black">
                    {{ $currentAsset }}
                </td>
            @endforeach
        </tr>
        <tr><td></td></tr>

        {{-- Non-current Assets --}}
        <tr>
            <td colspan="{{ count($labels) + 1 }}" style="font-weight: bold; border-bottom: 1px solid black">Non-current Assets</td>
        </tr>
        @foreach($nonCurrentAssets as $account)
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
        <tr>
            <td style="font-weight: bold; border-top: 1px solid black">Total Non-current Assets</td>
            @foreach($totalNonCurrentAssets as $nonCurrentAsset)
                <td data-format='#,##0.00;(#,##0.00)' style="font-weight: bold; text-align: right; border-top: 1px solid black">
                    {{ $nonCurrentAsset }}
                </td>
            @endforeach
        </tr>
    </tbody>

    <tfoot>
        <tr>
            <td style="font-weight: bold; border-top: 1px solid black">Total Assets</td>
            @foreach($totalAssets as $asset)
                <td data-format='#,##0.00;(#,##0.00)' style="font-weight: bold; text-align: right; border-top: 1px solid black">
                    {{ $asset }}
                </td>
            @endforeach
        </tr>
    </tfoot>
</table>

{{-- Liabilities Table --}}
<table>
    <thead>
    <tr>
        <th style="font-weight: bold; text-align: left">
            LIABILITIES
        </th>
        @foreach($labels as $dateLabel)
            <th style="font-weight: bold; text-align: right">
                {{ $dateLabel }}
            </th>
        @endforeach
    </tr>
    </thead>

    <tbody>
    {{-- Current Liabilities --}}
    <tr>
        <td colspan="{{ count($labels) + 1 }}" style="font-weight: bold; border-bottom: 1px solid black">Current Liabilities</td>
    </tr>
    @foreach($currentLiabilities as $account)
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
    <tr>
        <td style="font-weight: bold; border-top: 1px solid black">Total Current Liabilities</td>
        @foreach($totalCurrentLiabilities as $currentLiability)
            <td data-format='#,##0.00;(#,##0.00)' style="font-weight: bold; text-align: right; border-top: 1px solid black">
                {{ $currentLiability }}
            </td>
        @endforeach
    </tr>
    <tr><td></td></tr>

    {{-- Non-current Liabilities --}}
    <tr>
        <td colspan="{{ count($labels) + 1 }}" style="font-weight: bold; border-bottom: 1px solid black">Non-current Liabilities</td>
    </tr>
    @foreach($nonCurrentLiabilities as $account)
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
    <tr>
        <td style="font-weight: bold; border-top: 1px solid black">Total Non-current Liabilities</td>
        @foreach($totalNonCurrentLiabilities as $nonCurrentLiability)
            <td data-format='#,##0.00;(#,##0.00)' style="font-weight: bold; text-align: right; border-top: 1px solid black">
                {{ $nonCurrentLiability }}
            </td>
        @endforeach
    </tr>
    </tbody>

    <tfoot>
    <tr>
        <td style="font-weight: bold; border-top: 1px solid black">Total Liabilities</td>
        @foreach($totalLiabilities as $liability)
            <td data-format='#,##0.00;(#,##0.00)' style="font-weight: bold; text-align: right; border-top: 1px solid black">
                {{ $liability }}
            </td>
        @endforeach
    </tr>
    </tfoot>
</table>

{{-- Equity Table --}}
<table>
    <thead>
    <tr>
        <th style="font-weight: bold; text-align: left; border-bottom: 1px solid black">
            EQUITIES
        </th>
        @foreach($labels as $dateLabel)
            <th style="font-weight: bold; text-align: right; border-bottom: 1px solid black">
                {{ $dateLabel }}
            </th>
        @endforeach
    </tr>
    </thead>

    <tbody>
    @foreach($equityAccounts as $account)
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
    <tr>
        <td style="font-weight: bold; border-top: 1px solid black">Total Equities</td>
        @foreach($totalEquities as $equity)
            <td data-format='#,##0.00;(#,##0.00)' style="font-weight: bold; text-align: right; border-top: 1px solid black">
                {{ $equity }}
            </td>
        @endforeach
    </tr>

    <tfoot>
    <tr>
        <td style="font-weight: bold; border-top: 1px solid black">Total Liabilities + Equity</td>
        @foreach($totalLiabilitiesEquities as $liabilityEquity)
            <td data-format='#,##0.00;(#,##0.00)' style="font-weight: bold; text-align: right; border-top: 1px solid black">
                {{ $liabilityEquity }}
            </td>
        @endforeach
    </tr>
    </tfoot>
</table>
