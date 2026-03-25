<?php
// generate_pdf.php — Root-level PDF export (DOMPDF)
error_reporting(E_ALL);
ini_set('display_errors', 1);
$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    die('
    <div style="font-family:sans-serif;padding:40px;max-width:600px;margin:0 auto">
      <h2>⚠️ DOMPDF Not Installed</h2>
      <p>Run in your project root (<code>The-Vingo.com/</code>):</p>
      <pre style="background:#f5f5f5;padding:16px;border-radius:8px">composer require dompdf/dompdf</pre>
      <p><a href="admin/dashboard.php">← Back to Dashboard</a></p>
    </div>');
}

require_once $autoload;
use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/includes/db.php';

$result = $conn->query(
    "SELECT d.name, d.price, d.image, d.availability, c.name AS category
     FROM dishes d
     JOIN categories c ON c.id = d.category_id
     ORDER BY c.name, d.name"
);
$dishes  = $result->fetch_all(MYSQLI_ASSOC);

/* Group by category */
$grouped = [];
foreach ($dishes as $d) {
    $grouped[$d['category']][] = $d;
}

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing:border-box; margin:0; padding:0; }
  body  { font-family: DejaVu Sans, sans-serif; font-size:13px; color:#2d3748; padding:22px; }
  h1    { text-align:center; font-size:22px; color:#6c63ff; margin-bottom:4px; }
  .sub  { text-align:center; color:#718096; font-size:11px; margin-bottom:26px; }
  .cat  { font-size:13px; font-weight:bold; color:#6c63ff; border-bottom:2px solid #6c63ff;
          padding-bottom:4px; margin:20px 0 10px; text-transform:uppercase; letter-spacing:.8px; }
  table { width:100%; border-collapse:collapse; margin-bottom:10px; }
  th    { background:#f0f2f5; padding:8px 10px; text-align:left; font-size:10px;
          text-transform:uppercase; letter-spacing:.5px; color:#718096; border-bottom:1px solid #e2e8f0; }
  td    { padding:8px 10px; border-bottom:1px solid #e2e8f0; vertical-align:middle; }
  tr:last-child td { border-bottom:none; }
  .img-cell img { width:46px; height:46px; object-fit:cover; border-radius:5px; }
  .no-img       { width:46px; height:46px; background:#f0f2f5; border-radius:5px;
                  display:inline-block; text-align:center; line-height:46px; font-size:20px; }
  .price        { color:#6c63ff; font-weight:700; }
  .av-yes       { color:#27ae60; font-weight:600; }
  .av-no        { color:#e74c3c; font-weight:600; }
  .footer       { margin-top:30px; text-align:center; color:#a0aec0; font-size:10px; }
</style>
</head>
<body>
<h1>🍴 Our Menu</h1>
<div class="sub">Generated on <?= date('d M Y, h:i A') ?></div>

<?php foreach ($grouped as $cat => $items): ?>
  <div class="cat"><?= htmlspecialchars($cat) ?></div>
  <table>
    <thead>
      <tr><th width="60">Image</th><th>Dish</th><th>Price</th><th>Availability</th></tr>
    </thead>
    <tbody>
      <?php foreach ($items as $d):
        $img_path = __DIR__ . '/uploads/' . $d['image'];
        $has_img  = $d['image'] && file_exists($img_path);
        $type     = $has_img ? pathinfo($img_path, PATHINFO_EXTENSION) : '';
      ?>
      <tr>
        <td class="img-cell">
          <?php 
          $is_webp  = strtolower($type) === 'webp';
          $can_webp = function_exists('imagecreatefromwebp');
          
          if ($has_img && (!$is_webp || $can_webp)):
            $b64 = base64_encode(file_get_contents($img_path));
          ?>
            <img src="data:image/<?= $type ?>;base64,<?= $b64 ?>" alt="">
          <?php else: ?>
            <span class="no-img">🍽️</span>
          <?php endif; ?>
        </td>
        <td><?= htmlspecialchars($d['name']) ?></td>
        <td class="price">₹<?= number_format($d['price'], 2) ?></td>
        <td class="<?= $d['availability']==='Available' ? 'av-yes' : 'av-no' ?>">
          <?= $d['availability'] ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endforeach; ?>

<div class="footer">The-Vingo.com Menu — <?= date('Y') ?></div>
</body>
</html>
<?php
$html = ob_get_clean();

$opts = new Options();
$opts->set('defaultFont', 'DejaVu Sans');
$opts->set('isRemoteEnabled', false);

$dompdf = new Dompdf($opts);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('menu_' . date('Ymd') . '.pdf', ['Attachment' => true]);

