@include('reports_pdf.header')

    <div class="row" style="padding-top: -3%">

        <h3 align="center" style="color:SteelBlue;">Total Monthly Expenses</h3>


        <div class="row">

            <table width="100%" style="width:100%" border="1" Cellspacing="0">

                <thead>
                    <tr>
                        <th bgcolor="#bfbfbf" style="text-align:center" >No.</th>
                        <th bgcolor="#bfbfbf" style="text-align:center">Month</th>
                        <th bgcolor="#bfbfbf" style="text-align:center">Amount</th>

                    </tr>
                </thead>
                <tbody>
                    <?php $i = 0 ?>

                    @foreach($data as $d)
                        <?php $i++ ?>

                        <tr>
                            <td style="text-align:center">{{ $i }}</td>
                            <td>{{$d->month}}</td>
                            <td style="text-align:center">{{number_format($d->amount,2)}}</td>
                        </tr>
                    @endforeach

                </tbody>

            </table>

        </div>

    </div>

@include('reports_pdf.footer')
