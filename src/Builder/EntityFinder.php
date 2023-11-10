<?php

namespace Phnet\Builder;

class EntityFinder
{
    public static function find(string $path)
    {
        $entities = [];
        $content = php_strip_whitespace($path);
        $prepared = preg_replace("/^<\?(php)?/", '', $content);
        if (preg_match_all(
            Constants::NAMESPACE_REGEX,
            $prepared,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $namespaceContent = $match['content_t1'] ?? $match['content_t2'] ?? '';
                preg_match_all(Constants::ENTITY_REGEX, $namespaceContent, $namespaceMatches, PREG_SET_ORDER);

                foreach ($namespaceMatches as $namespaceMatch) {
                    $entity = $match['namespace'] . '\\' . $namespaceMatch['name'];
                    $entities[] = $entity;
                }
            }
        }

        return $entities;
    }

    public static function findByTokens(string $path)
    {
        $content = file_get_contents($path);
        $tokens = token_get_all($content);
        $entities = [];
        $version = phpversion();

        $namespace = null;
        $state = null;
        $doubleColon = false;
        foreach ($tokens as $token) {
            if (!is_array($token)) {
                $state = 'empty';
                continue;
            }
            switch ($token[0]) {
                case T_DOUBLE_COLON:
                    $doubleColon = true;
                    break;
                case T_NAMESPACE:
                    $namespace = '';
                    $state = 'namespace';
                    break;
                case T_EXTENDS:
                    $state = 'extends';
                    break;
                case T_IMPLEMENTS:
                    $state = 'implement';
                    break;
                case T_CLASS:
                case T_TRAIT:
                case T_INTERFACE:
                    if(!$doubleColon)
                        $state = 'entity';
                    break;
                case T_STRING:
                    switch ($state) {
                        case 'namespace':
                            $namespace .= $token[1];
                            break;
                        case 'entity':
                            $entities[] = (empty($namespace) ? '' : $namespace . "\\") . $token[1];
                            break;
                    }
                    break;
                case T_NS_SEPARATOR:
                    switch ($state) {
                        case 'namespace':
                            $namespace .= '\\';
                            break;
                    }
                    break;
                default:
                    $doubleColon = false;
            }

            if ($version >= 8 && in_array($token[0], [T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED]))
                $namespace = $token[1];

        }

        return $entities;
    }
}