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

        $newUl = $newDom->find('ul', 0);
        $oldUl = $oldDom->find('ul', 0);

        $operations = $this->getListItemOperations($oldUl, $newUl);
    }

    protected function getListItemOperations($oldUl, $newUl)
    {
        $lineHashMap = [];
        $lines = [];
        $hashes = [];
        $newLineText = [];
        foreach ($newUl->children() as $index => $li) {
            $line = $index + 1;
            $newLineText[$line] = $li->innertext;
            $hash = hash('md5', $li->innertext);
            $lineHashMap[] = ['serial' => $line, 'hash' => $hash];
            $lines[] = $line;
            $hashes[] = $hash;
        }

        array_multisort($hashes, SORT_ASC, $lines, SORT_ASC, $lineHashMap);


        $e = [['serial' => 0, 'last' => true]];
        foreach ($lineHashMap as $index => $lineHash) {
            $last = $index == (count($lineHashMap) - 1) ||
                $lineHash['hash'] != $lineHashMap[$index + 1]['hash'];

            $e[] = ['serial' => $lineHash['serial'], 'last' => $last];
        }

        $oldLineHashMap = [];
        $oldLines = [];
        $oldHashes = [];
        $oldLineText = [];
        $p = [];

        foreach ($oldUl->children() as $index => $li) {
            $line = $index + 1;
            $oldLineText[$line] = $li->innertext;
            $hash = hash('md5', $li->innertext);
            $val = 0;
            foreach ($lineHashMap as $j => $lineHash) {
                if ($hash == $lineHash['hash'] && $e[$j]['last'] === true) {
                    $val = $j + 1;
                    break;
                }
            }
            $p[$line] = $val;
            $oldLines[] = $line;
            $oldHashes[$line] = $hash;
        }

        $m = count($oldLines);
        $n = count($lines);

        /* @var $k Candidate[] */
        $k = [];
        $k[] = new Candidate(0, 0, null);
        $k[] = new Candidate($m + 1, $n + 1, null);
        $kIndex = 0;

        for ($i = 1; $i <= $m; $i++) {
            $pIndex = $p[$i];

            if ($pIndex !== 0) {
                $this->merge($k, $kIndex, $i, $e, $pIndex);
            }
        }

        // Let J be a vector of integers.
        $j = array_pad([], $m + 1, 0);

        /* @var $c Candidate */
        $c = $k[$kIndex];
        while (null !== $c) {
            $j[$c->getA()] = $c->getB();
            $c = $c->getPrevious();
        }

        for ($i = 1; $i <= $m; $i++) {
            if ($j[$i] !== 0 && $oldLineText[$i] !== $newLineText[$j[$i]]) {
                $j[$i] = 0;
            }
        }

        $operations = [];
        $lineInOld = 0;
        $lineInNew = 0;
        $j[$m + 1] = $n + 1;
        foreach ($j as $i => $match) {
            if ($match !== 0) {
                if ($match > ($lineInNew + 1) && $i === ($lineInOld + 1)) {
                    // Add items before this
                    $operations[] = new Operation(Operation::ADDED, 0, 0, $lineInNew + 1, $match - 1);
                } elseif ($i > ($lineInOld + 1) && $match === ($lineInNew + 1)) {
                    // Delete items before this
                    $operations[] = new Operation(Operation::DELETED, $lineInOld + 1, $i - 1, $lineInNew, $lineInNew);
                } elseif ($match !== ($lineInNew + 1) && $i !== ($lineInOld + 1)) {
                    // Change
                    $operations[] = new Operation(Operation::CHANGED, $lineInOld + 1, $i - 1, $lineInNew + 1, $match - 1);
                }

                $lineInNew = $match;
                $lineInOld = $i;
            }
        }

        return $operations;
    }

    /**
     * @param Candidate[] $k
     * @param int $kIndex
     * @param int $i
     * @param array $e
     * @param int $p
     */
    protected function merge(&$k, &$kIndex, $i, &$e, $p)
    {
        $r = 0;
        $c = $k[0];

        // Step 2
        while (true) {
            // Let j = E[p].serial
            $j = $e[$p]['serial'];

            // Search K[r:k] for an element K[s] such that K[s] -> b < j and K[s+1] -> b > j
            $s = null;
            for ($index = $r; $index <= $kIndex; $index++) {
                if ($k[$index]->getB() < $j && $k[$index + 1]->getB() > $j) {
                    $s = $index;
                    break;
                }
            }

            // If such an element is found do steps 4 and 5
            if (null !== $s) {
                if ($k[$s + 1]->getB() > $j) {
                    $k[$r] = $c;
                    $r = $s + 1;
                    $c = new Candidate($i, $j, $k[$s]);
                }

                if ($s === $kIndex) {
                    $k[$kIndex + 2] = $k[$kIndex + 1]; // move fence
                    $kIndex++;
                    break; // break out of step 2's loop
                }

            }

            if ($e[$p]['last']) {
                break; // break out of step 2's loop
            } else {
                $p++;
            }
        }

        // Set K[r] <-- c
        $k[$r] = $c;
    }
}
