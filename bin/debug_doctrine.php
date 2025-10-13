<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/bootstrap.php';

$kernel = new App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();
echo 'EM: ', get_class($em), PHP_EOL;
$driver = $em->getConfiguration()->getMetadataDriverImpl();
echo 'Driver: ', get_class($driver), PHP_EOL;
$all = $em->getMetadataFactory()->getAllMetadata();
echo 'Metadata count: ', count($all), PHP_EOL;
foreach ($all as $m) { echo $m->name, PHP_EOL; }

// Raw AttributeDriver probe
try {
    $attr = new Doctrine\ORM\Mapping\Driver\AttributeDriver([__DIR__ . '/../src/Entity']);
    $classes = $attr->getAllClassNames();
    echo 'AttributeDriver classes: ', json_encode($classes), PHP_EOL;
} catch (Throwable $e) {
    echo 'AttributeDriver error: ', $e->getMessage(), PHP_EOL;
}
