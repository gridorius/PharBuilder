<?php
$rest_index = null;
$options = getopt('o:', [], $rest_index);
$pos_args = array_slice($argv, $rest_index);

(new PharBuilder\Builder($pos_args[0], $options['o']))->build();