<div>
    <script type="text/php">
        if ( isset($pdf) ) {
            $font = $fontMetrics->getFont("Arial", "bold");
            $y = $pdf->get_height()-35;
            $date = date('d.m.Y');
            $pdf->page_text(500, $y, "Page {PAGE_NUM}/{PAGE_COUNT}", $font, 7, array(0, 0, 0));
            $pdf->page_text(50, $y, "Printed: ".$date , $font, 7, array(0, 0, 0));

        }
    </script>
</div>

</body>


</html>