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
        $newLineText = [];
        foreach ($ul->children() as $index => $li) {
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

        $oldUl = $oldDom->find('ul', 0);

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
        $lineInOld = 1;
        $lineInNew = 1;
        for ($i = 1; $i <= $m; $i++) {
            $match = $j[$i];

            if ($match !== 0) {
                if ($match > $lineInNew && $i === $lineInOld) {
                    // Add items before this
                } elseif ($i > $lineInOld && $match === $lineInNew) {
                    // Delete items before this
                }
                if ($lineInNew === $match) {
                    // Nothing new to add
                }

                if ($lineInOld === $i) {
                    // Nothing to remove
                }
            }


        }

        var_dump($j);
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
