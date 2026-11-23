<!doctype html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport"
              content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Income Statement Report</title>

        <style>
            /* PDF settings */
            @page {
                margin: 72pt; /* 1 inch margin on all sides of the paper */
            }

            body {
                font-family: 'DejaVu Sans', sans-serif;
                font-size: 12px;
            }

            body, h3, p {
                margin: 0;
                padding: 0;
            }

            header {
                position: fixed;
                top: -59pt;
                left: 0;
                right: 0;
                text-align: center;
            }

            table {
                width: 100%;
                margin: 0 auto;
                border-collapse: collapse;
                border-spacing: 1px;
            }

            thead > tr:first-child > th {
                text-align: center;
                background-color: black;
                color: white;
            }

            tbody > tr:last-child > td {
                border-bottom: 2px solid black;
            }

            .col-account-name {
                width: 40%;
                text-align: left;
                border-bottom: 2px solid black;
            }

            .col-balance {
                width: 20%;
                text-align: right;
                border-bottom: 2px solid black;
            }
        </style>
    </head>
    <body>
        {{-- Company Name and Date Range --}}
        <header>
            <h3>{{$companyName}}</h3>
            <p>Income Statement Report</p>
            <p>{{"For the period of $labels[0]"}}</p>
        </header>

        <main>
            {{-- Revenue Table --}}
            <table style="margin-bottom: 25px">
                <thead>
                    <tr>
                        <th colspan="{{ count($labels) + 1 }}">REVENUE</th>
                    </tr>
                    <tr>
                        <th class="col-account-name">Account Name</th>
                        @foreach($labels as $dateLabel)
                            <th class="col-balance">{{ $dateLabel }}</th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                @foreach($revenueAccounts as $account)
                    <tr>
                        <td>{{$account['name']}}</td>
                        <td style="text-align: right">
                            {{
                                $account['balance'] >= 0
                                    ? Number::currency($account['balance'], in: 'PHP', locale: 'en_PH')
                                    : '(' . Number::currency(abs($account['balance']), in: 'PHP', locale: 'en_PH') . ')'
                            }}
                        </td>
                        @if(isset($account['previousBalances']))
                            @foreach($account['previousBalances'] as $previousBalance)
                                <td style="text-align: right">
                                    {{
                                        $previousBalance >= 0
                                            ? Number::currency($previousBalance, in: 'PHP', locale: 'en_PH')
                                            : '(' . Number::currency(abs($previousBalance), in: 'PHP', locale: 'en_PH') . ')'
                                    }}
                                </td>
                            @endforeach
                        @endif
                    </tr>
                @endforeach
                </tbody>

                <tfoot>
                    <tr>
                        <td style="font-weight: bold">TOTAL REVENUE</td>
                        @foreach($totalRevenue as $revenue)
                            <td style="text-align: right; font-weight: bold;">
                                {{
                                    $revenue >= 0
                                        ? Number::currency($revenue, in: 'PHP', locale: 'en_PH')
                                        : '(' . Number::currency(abs($revenue), in: 'PHP', locale: 'en_PH') . ')'
                                }}
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>

            {{-- Expense Table --}}
            <table>
                <thead>
                    <tr>
                        <th colspan="{{ count($labels) + 1 }}">EXPENSES</th>
                    </tr>
                    <tr>
                        <th class="col-account-name">Account Name</th>
                        @foreach($labels as $dateLabel)
                            <th class="col-balance">{{ $dateLabel }}</th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                @foreach($expenseAccounts as $account)
                    <tr>
                        <td>{{$account['name']}}</td>
                        <td style="text-align: right">
                            {{
                                $account['balance'] >= 0
                                    ? Number::currency($account['balance'], in: 'PHP', locale: 'en_PH')
                                    : '(' . Number::currency(abs($account['balance']), in: 'PHP', locale: 'en_PH') . ')'
                            }}
                        </td>
                        @if(isset($account['previousBalances']))
                            @foreach($account['previousBalances'] as $previousBalance)
                                <td style="text-align: right">
                                    {{
                                        $previousBalance >= 0
                                            ? Number::currency($previousBalance, in: 'PHP', locale: 'en_PH')
                                            : '(' . Number::currency(abs($previousBalance), in: 'PHP', locale: 'en_PH') . ')'
                                    }}
                                </td>
                            @endforeach
                        @endif
                    </tr>
                @endforeach
                </tbody>

                <tfoot>
                    <tr>
                        <td style="font-weight: bold">TOTAL EXPENSES</td>
                        @foreach($totalExpense as $expense)
                            <td style="text-align: right; font-weight: bold;">
                                {{
                                    $expense >= 0
                                        ? Number::currency($expense, in: 'PHP', locale: 'en_PH')
                                        : '(' . Number::currency(abs($expense), in: 'PHP', locale: 'en_PH') . ')'
                                }}
                            </td>
                        @endforeach
                    </tr>

                    <tr>
                        <td style="font-weight: bold; background-color: black; color: white">NET PROFIT/LOSS</td>
                        @foreach($netTotal as $total)
                            <td style="text-align: right; font-weight: bold; background-color: black; color: white">
                                {{
                                    $total >= 0
                                        ? Number::currency($total, in: 'PHP', locale: 'en_PH')
                                        : '(' . Number::currency(abs($total), in: 'PHP', locale: 'en_PH') . ')'
                                }}
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>
        </main>

        <script type="text/php">
            if (isset($pdf)) {
                // Page text and styling
                $text = "Page {PAGE_NUM} of {PAGE_COUNT}";
                $size = 8;
                $font = $fontMetrics->getFont("DejaVu Sans");

                // Manual calculation on where to put the page text
                $textWidth = $fontMetrics->get_text_width($text, $font, $size);
                $x = $pdf->get_width() - 116;
                $y = $pdf->get_height() - 52;

                // Render page text
                $pdf->page_text($x, $y, $text, $font, $size);
            }
        </script>
    </body>
</html>
