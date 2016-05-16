<?php

namespace Caxy\HtmlDiff;


use Sunra\PhpSimple\HtmlDomParser;

class ListDiffLines extends ListDiff
{
    public function build()
    {
        $this->listByLines($this->oldText, $this->newText);
    }

    protected function listByLines($old, $new)
    {
        /* @var $newDom \simple_html_dom */
        $newDom = HtmlDomParser::str_get_html($new);
        /* @var $oldDom \simple_html_dom */
        $oldDom = HtmlDomParser::str_get_html($old);

        $ul = $newDom->find('ul', 0);

        $lineHashMap = [];
        $lines = [];
        $hashes = [];
        foreach ($ul->children() as $index => $li) {
            $line = $index + 1;
            $hash = hash('md5', $li->innertext);
            $lineHashMap[] = ['serial' => $line, 'hash' => $hash];
            $lines[] = $line;
            $hashes[] = $hash;
        }

        array_multisort($hashes, SORT_ASC, $lines, SORT_ASC, $lineHashMap);

        $e = [[0, true]];
        foreach ($lineHashMap as $index => $lineHash) {
            $last = $index == (count($lineHashMap) - 1) ||
                $lineHash['hash'] != $lineHashMap[$index + 1]['hash'];

            $e[] = ['serial' => $lineHash['serial'], 'last' => $last];
        }

        $oldUl = $oldDom->find('ul', 0);

        $oldLineHashMap = [];
        $oldLines = [];
        $oldHashes = [];
        $p = [];

        foreach ($oldUl->children() as $index => $li) {
            $line = $index + 1;
            $hash = hash('md5', $li->innertext);
            $val = 0;
            foreach ($lineHashMap as $lineHash) {
                if ($hash == $lineHash['hash'] && $e[$lineHash['serial'] - 1]) {
                    $val = $lineHash['serial'];
                    break;
                }
            }
            $p[$line] = $val;
        }

        var_dump($p);
    }
}
