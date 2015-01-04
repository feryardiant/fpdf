<?php
$font = array(
    'type' => 'Core',
    'name' => 'Courier-Bold',
    'up'   => -100,
    'ut'   => 50,
);

for ($i = 0; $i <= 255; $i++) {
    $font['cw'][chr($i)] = 600;
}

return $font;
