<table>
    <thead>
    <tr>
        <th>Account Name</th>
        <th>Core Type</th>
        <th>Account Type</th>
        @foreach($labels as $dateLabel)
            <th>{{ $dateLabel }}</th>
        @endforeach
    </tr>
    </thead>

    <tbody>
    @foreach($currentAssets as $account)
        <tr>
            <td>{{ $account['name'] }}</td>
            <td>Asset</td>
            <td>Current Asset</td>
            <td>{{ $account['balance'] }}</td>
            @if(isset($account['previousBalances']))
                @foreach($account['previousBalances'] as $previousBalance)
                    <td>{{ $previousBalance }}</td>
                @endforeach
            @endif
        </tr>
    @endforeach
    @foreach($nonCurrentAssets as $account)
        <tr>
            <td>{{ $account['name'] }}</td>
            <td>Asset</td>
            <td>Non-current Asset</td>
            <td>{{ $account['balance'] }}</td>
            @if(isset($account['previousBalances']))
                @foreach($account['previousBalances'] as $previousBalance)
                    <td>{{ $previousBalance }}</td>
                @endforeach
            @endif
        </tr>
    @endforeach
    @foreach($currentLiabilities as $account)
        <tr>
            <td>{{ $account['name'] }}</td>
            <td>Liability</td>
            <td>Current Liability</td>
            <td>{{ $account['balance'] }}</td>
            @if(isset($account['previousBalances']))
                @foreach($account['previousBalances'] as $previousBalance)
                    <td>{{ $previousBalance }}</td>
                @endforeach
            @endif
        </tr>
    @endforeach
    @foreach($nonCurrentLiabilities as $account)
        <tr>
            <td>{{ $account['name'] }}</td>
            <td>Liability</td>
            <td>Non-current Liability</td>
            <td>{{ $account['balance'] }}</td>
            @if(isset($account['previousBalances']))
                @foreach($account['previousBalances'] as $previousBalance)
                    <td>{{ $previousBalance }}</td>
                @endforeach
            @endif
        </tr>
    @endforeach
    @foreach($equityAccounts as $account)
        <tr>
            <td>{{ $account['name'] }}</td>
            <td>Equity</td>
            <td>Equity</td>
            <td>{{ $account['balance'] }}</td>
            @if(isset($account['previousBalances']))
                @foreach($account['previousBalances'] as $previousBalance)
                    <td>{{ $previousBalance }}</td>
                @endforeach
            @endif
        </tr>
    @endforeach
    </tbody>
</table>
