<table>
    <thead>
        <tr>
            <th>Account Name</th>
            <th>Account Type</th>
            <th>Debit</th>
            <th>Credit</th>
        </tr>
    </thead>

    <tbody>
    @foreach($assetAccounts as $account)
        <tr>
            <td>{{ $account['name'] }}</td>
            <td>Asset</td>
            <td>{{ $account['debit'] }}</td>
            <td>{{ $account['credit'] }}</td>
        </tr>
    @endforeach
    @foreach($liabilityAccounts as $account)
        <tr>
            <td>{{ $account['name'] }}</td>
            <td>Liability</td>
            <td>{{ $account['debit'] }}</td>
            <td>{{ $account['credit'] }}</td>
        </tr>
    @endforeach
    @foreach($equityAccounts as $account)
        <tr>
            <td>{{ $account['name'] }}</td>
            <td>Equity</td>
            <td>{{ $account['debit'] }}</td>
            <td>{{ $account['credit'] }}</td>
        </tr>
    @endforeach
    @foreach($revenueAccounts as $account)
        <tr>
            <td>{{ $account['name'] }}</td>
            <td>Revenue</td>
            <td>{{ $account['debit'] }}</td>
            <td>{{ $account['credit'] }}</td>
        </tr>
    @endforeach
    @foreach($expenseAccounts as $account)
        <tr>
            <td>{{ $account['name'] }}</td>
            <td>Expense</td>
            <td>{{ $account['debit'] }}</td>
            <td>{{ $account['credit'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
