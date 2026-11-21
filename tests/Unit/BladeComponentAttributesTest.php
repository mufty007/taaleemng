<?php

use Symfony\Component\Finder\Finder;

/*
 * Blade does not compile directives such as @js inside the attributes of a
 * <x-component> tag: the raw text reaches the browser and the Alpine handler
 * breaks silently. Use {{ \Illuminate\Support\Js::from(...) }} there instead.
 */
it('never puts Blade directives inside component tag attributes', function () {
    $problems = [];

    foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
        $source = $file->getContents();
        $offset = 0;

        while (preg_match('/<x-[a-z0-9.:-]+/i', $source, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $start = $match[0][1];
            $quote = null;

            for ($end = $start + strlen($match[0][0]); $end < strlen($source); $end++) {
                $character = $source[$end];

                if ($quote !== null) {
                    $quote = $character === $quote ? null : $quote;
                } elseif ($character === '"' || $character === "'") {
                    $quote = $character;
                } elseif ($character === '>') {
                    break;
                }
            }

            if (preg_match('/@(js|json|if|can|foreach|php|class)\b/', substr($source, $start, $end - $start), $directive)) {
                $problems[] = $file->getRelativePathname().':'.(substr_count(substr($source, 0, $start), "\n") + 1).' @'.$directive[1];
            }

            $offset = $start + 3;
        }
    }

    expect($problems)->toBe([]);
});

/*
 * An inline @php(...) followed later by a @php ... @endphp block in the same view
 * is read as one long block, and the view fails to compile.
 */
it('never mixes an inline @php before a @php block in one view', function () {
    $problems = [];

    foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
        $source = $file->getContents();
        $inline = preg_match('/@php\(/', $source, $first, PREG_OFFSET_CAPTURE) ? $first[0][1] : null;

        if ($inline !== null && preg_match('/@php(?!\()\s/', $source, $block, PREG_OFFSET_CAPTURE, $inline)) {
            $problems[] = $file->getRelativePathname();
        }
    }

    expect($problems)->toBe([]);
});
