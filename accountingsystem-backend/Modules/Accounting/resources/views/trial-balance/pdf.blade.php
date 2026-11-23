<!doctype html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport"
              content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Trial Balance Report</title>

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

            .col-account-name {
                width: 40%;
                text-align: left;
                background-color: black;
                color: white;
                font-weight: bold;
            }

            .col-balance {
                width: 20%;
                text-align: right;
                background-color: black;
                color: white;
                font-weight: bold;
            }

            .row-account-type {
                text-align: left;
                font-weight: bold;
                border-top: 2px solid black;
                border-bottom: 2px solid black;
            }

            .cell-account-name {
                padding-left: 16px;
            }
        </style>
    </head>
    <body>
        {{-- Company Name and Date --}}
        <header>
            <h3>{{$companyName}}</h3>
            <p>Trial Balance Report</p>
            <p>{{$dateText}}</p>
        </header>

        <main>
            <table>
                <thead>
                    <tr>
                        <th class="col-account-name">ACCOUNT</th>
                        <th class="col-balance">NET DEBIT (Dr)</th>
                        <th class="col-balance">NET CREDIT (Cr)</th>
                    </tr>
                </thead>

                <tbody>
                    {{-- Asset Accounts --}}
                    <tr>
                        <td colspan="3" class="row-account-type">Asset</td>
                    </tr>
                    @foreach($assetAccounts as $account)
                        <tr>
                            <td class="cell-account-name">{{$account['name']}}</td>
                            <td style="text-align: right">
                                {{
                                    $account['debit'] > 0
                                        ? Number::currency($account['debit'], in: 'PHP', locale: 'en_PH')
                                        : ''
                                }}
                            </td>
                            <td style="text-align: right">
                                {{
                                    $account['credit'] > 0
                                        ? Number::currency($account['credit'], in: 'PHP', locale: 'en_PH')
                                        : ''
                                }}
                            </td>
                        </tr>
                    @endforeach

                    {{-- Liability Accounts --}}
                    <tr>
                        <td colspan="3" class="row-account-type">Liability</td>
                    </tr>
                    @foreach($liabilityAccounts as $account)
                        <tr>
                            <td class="cell-account-name">{{$account['name']}}</td>
                            <td style="text-align: right">
                                {{
                                    $account['debit'] > 0
                                        ? Number::currency($account['debit'], in: 'PHP', locale: 'en_PH')
                                        : ''
                                }}
                            </td>
                            <td style="text-align: right">
                                {{
                                    $account['credit'] > 0
                                        ? Number::currency($account['credit'], in: 'PHP', locale: 'en_PH')
                                        : ''
                                }}
                            </td>
                        </tr>
                    @endforeach

                    {{-- Equity Accounts --}}
                    <tr>
                        <td colspan="3" class="row-account-type">Equity</td>
                    </tr>
                    @foreach($equityAccounts as $account)
                        <tr>
                            <td class="cell-account-name">{{$account['name']}}</td>
                            <td style="text-align: right">
                                {{
                                    $account['debit'] > 0
                                        ? Number::currency($account['debit'], in: 'PHP', locale: 'en_PH')
                                        : ''
                                }}
                            </td>
                            <td style="text-align: right">
                                {{
                                    $account['credit'] > 0
                                        ? Number::currency($account['credit'], in: 'PHP', locale: 'en_PH')
                                        : ''
                                }}
                            </td>
                        </tr>
                    @endforeach

                    {{-- Revenue Accounts --}}
                    <tr>
                        <td colspan="3" class="row-account-type">Revenue</td>
                    </tr>
                    @foreach($revenueAccounts as $account)
                        <tr>
                            <td class="cell-account-name">{{$account['name']}}</td>
                            <td style="text-align: right">
                                {{
                                    $account['debit'] > 0
                                        ? Number::currency($account['debit'], in: 'PHP', locale: 'en_PH')
                                        : ''
                                }}
                            </td>
                            <td style="text-align: right">
                                {{
                                    $account['credit'] > 0
                                        ? Number::currency($account['credit'], in: 'PHP', locale: 'en_PH')
                                        : ''
                                }}
                            </td>
                        </tr>
                    @endforeach

                    {{-- Expense Accounts --}}
                    <tr>
                        <td colspan="3" class="row-account-type">Expense</td>
                    </tr>
                    @foreach($expenseAccounts as $account)
                        <tr>
                            <td class="cell-account-name">{{$account['name']}}</td>
                            <td style="text-align: right">
                                {{
                                    $account['debit'] > 0
                                        ? Number::currency($account['debit'], in: 'PHP', locale: 'en_PH')
                                        : ''
                                }}
                            </td>
                            <td style="text-align: right">
                                {{
                                    $account['credit'] > 0
                                        ? Number::currency($account['credit'], in: 'PHP', locale: 'en_PH')
                                        : ''
                                }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>

                {{-- Grand Totals --}}
                <tfoot>
                    <tr>
                        <td class="col-account-name">TOTAL</td>
                        <td class="col-balance">
                            {{
                                Number::currency($totalDebit, in: 'PHP', locale: 'en_PH')
                            }}
                        </td>
                        <td class="col-balance">
                            {{
                                Number::currency($totalCredit, in: 'PHP', locale: 'en_PH')
                            }}
                        </td>
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
