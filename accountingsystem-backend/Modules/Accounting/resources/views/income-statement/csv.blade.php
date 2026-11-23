<table>
    <thead>
        <tr>
            <th>Account Name</th>
            <th>Account Type</th>
            @foreach($labels as $dateLabel)
                <th>{{ $dateLabel }}</th>
            @endforeach
        </tr>
    </thead>

    <tbody>
    @foreach($revenueAccounts as $account)
        <tr>
            <td>{{ $account['name'] }}</td>
            <td>Revenue</td>
            <td>{{ $account['balance'] }}</td>
            @if(isset($account['previousBalances']))
                @foreach($account['previousBalances'] as $previousBalance)
                    <td>{{ $previousBalance }}</td>
                @endforeach
            @endif
        </tr>
    @endforeach
    @foreach($expenseAccounts as $account)
        <tr>
            <td>{{ $account['name'] }}</td>
            <td>Expense</td>
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
