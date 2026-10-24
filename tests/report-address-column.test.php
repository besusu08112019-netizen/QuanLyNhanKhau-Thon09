<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\SimplePdf;
use App\Core\SimpleXlsx;

function expect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$source = file_get_contents(BASE_PATH . '/app/Models/Report.php');
expect(is_string($source), 'Không đọc được Report.php');
expect(str_contains($source, "citizenListReport('Danh sách nhân khẩu', \$filters, true)"), 'Báo cáo nhân khẩu chưa bật cột địa chỉ');
expect(str_contains($source, "COALESCE(NULLIF(TRIM(c.current_address),''),h.address) AS effective_address"), 'Sai nguồn địa chỉ nhân khẩu');
expect(str_contains($source, "array_splice(\$headers, 3, 0, ['Địa chỉ'])"), 'Sai vị trí cột địa chỉ');
expect(!str_contains($source, "citizenListReport('Danh sách tạm trú', \$filters, true)"), 'Báo cáo tạm trú bị mở rộng ngoài phạm vi');
expect(!str_contains($source, "citizenListReport('Danh sách tạm vắng', \$filters, true)"), 'Báo cáo tạm vắng bị mở rộng ngoài phạm vi');

$longAddress = str_repeat('Địa chỉ dài cần xuống dòng đầy đủ ', 18);
$report = ['title'=>'Danh sách nhân khẩu','headers'=>['Mã hộ','Mã nhân khẩu','Họ tên','Địa chỉ'],'rows'=>[['H001','NK001','Nguyễn Văn A',$longAddress]],'totalRows'=>1];
$xlsx = (new SimpleXlsx())->output($report);
expect(str_contains($xlsx, 'wrapText="1"') && str_contains($xlsx, $longAddress), 'Excel không giữ đầy đủ địa chỉ');
$pdf = new SimplePdf('landscape');
$pdf->addTable($report['headers'], $report['rows']);
$pdfOutput = $pdf->output();
expect(str_starts_with($pdfOutput, '%PDF-') && strlen($pdfOutput) > 1000, 'Không tạo được PDF địa chỉ dài');

echo "report-address-column: OK\n";
