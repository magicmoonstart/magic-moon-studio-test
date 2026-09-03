<?php
/**
 * Exact widget content — corrections/translate-de
 *
 * Mostly German page copy, which is what this started as. It has since also
 * become the place for any correction that needs a widget's content restated
 * exactly, in whatever language — including trimming unwanted passages that
 * were appended to widgets holding genuine copy, where deleting the widget
 * would take the good content with it. Post 2813 is English for that reason.
 *
 * WHY THIS FILE EXISTS
 * corrections/page-clone copies a finished English layout onto its German
 * counterpart, which fixes the design and the background photographs but
 * leaves English text on a German page. This file supplies the German copy
 * that goes on top.
 *
 * KEYED BY WIDGET ID, NOT BY STRING MATCH
 * Each entry names the Elementor element id and the setting to overwrite:
 *   heading      -> title
 *   text-editor  -> editor
 *   button       -> text (and link)
 * Nothing is searched for, so whitespace, escaping and JSON encoding cannot
 * cause a near-miss. If an id is ever absent the run reports it by name
 * instead of silently doing nothing.
 *
 * HOUSE STYLE
 * The wording follows the studio's own already-translated piercing page
 * /ohrlaeppchen/ (post 607) rather than a fresh translation, so headings,
 * button labels, the IfSG paragraph and the three numbered steps read the same
 * across the piercing pages:
 *   "Unser Service" / "Was ist ein …-Piercing?" /
 *   "Gesellschaftlicher und historischer Hintergrund" /
 *   "Auswirkungen auf die Verschönerung — …" /
 *   "Häufige Herausforderungen — und warum Technik entscheidend ist:" /
 *   "So löst unser Studio das Problem — Ein medizinisch orientierter …prozess"
 * Buttons use "Terminanfrage" and, for the process section, "Beratungstermin",
 * pointing at /contact/ as the German pages do.
 *
 * Address form: informal "du/dein" throughout. The Ohrläppchen page mixes
 * "Sie" and "du" between steps; this page is internally consistent.
 */

if (!defined('ABSPATH')) exit;

/* Reusable markup fragments, so the pattern cannot drift between blocks. */
$S  = '<span style="font-weight: 400;">';
$LI = '<li style="font-weight: 400;" aria-level="1">';

$p = function ($text) use ($S) { return '<p>' . $S . $text . '</span></p>'; };
$b = function ($label, $text) use ($S, $LI) {
    return $LI . '<b>' . $label . '</b>' . $S . ' ' . $text . '</span></li>';
};

return array(

    /* ==================================================================
     * 667 — /navel-belly-button/  (Bauchnabel-Piercing)
     * Layout and photographs come from EN post 4797 via page-clone.
     * ================================================================== */
    667 => array(

        /* Page-level directives use a leading underscore and are applied
           outside the widget walk. The slug is deliberately NOT changed:
           /navel-belly-button/ is already in use and renaming it would break
           every existing link to the page. */
        '_post_title' => 'Bauchnabel-Piercing',

        '64cfa103' => array('title' => 'Unser Service'),
        '45126c76' => array('title' => 'Bauchnabel-Piercing'),
        '553eb734' => array('title' => 'Was ist ein Bauchnabel-Piercing?'),

        '123dd581' => array('editor' =>
            $p('Ein Bauchnabel-Piercing — auch Navel-Piercing genannt — ist eine Form der Körpermodifikation, bei der eine sterile Hohlnadel durch das Hautgewebe direkt über oder um den Bauchnabel geführt wird, um ein Schmuckstück aufzunehmen. Anders als bei Oberflächenpiercings sitzt ein korrekt platziertes Bauchnabel-Piercing durch die obere Nabelfalte, wobei eine dekorative Kugel oben sichtbar bleibt und der Bananenstab in der natürlichen Vertiefung ruht. Platzierung, Schmuckstärke und anatomische Beurteilung entscheiden gemeinsam darüber, ob ein Piercing gut verheilt, ausgewogen wirkt und langfristig erhalten bleibt. In einem professionellen Tattoo- und Piercing-Studio ist das kein Routineeingriff — es ist ein sorgfältig geplanter, an der Anatomie ausgerichteter Ablauf, der mit einer ausführlichen Beratung beginnt, bevor eine einzige Nadel vorbereitet wird.')
        ),

        '104b34ba' => array('text' => 'Terminanfrage', 'link' => array('url' => '/contact/')),

        '97e53ef' => array('title' => 'Gesellschaftlicher und historischer Hintergrund'),

        'ae55d9c' => array('editor' =>
            $p('Körperpiercing als Praxis reicht Tausende von Jahren zurück und findet sich auf nahezu jedem Kontinent. Frühe Zivilisationen nutzten Schmuck und Körperschmuck als Zeichen spiritueller Verbundenheit, sozialen Rangs und kultureller Zugehörigkeit. Im alten Ägypten wurde Nabelschmuck mit Königtum, Wohlstand und Ansehen der herrschenden Schichten verbunden. In südasiatischen Traditionen trug Taillen- und Bauchschmuck eine spirituelle Bedeutung, die mit dem sakralen Energiezentrum des Körpers verknüpft war und für Weiblichkeit, Fruchtbarkeit und Lebenskraft stand.')
          . $p('Als eigenständiger, moderner Eingriff gelangte das Bauchnabel-Piercing in den frühen 1990er Jahren in den westlichen Mainstream. Es entwickelte sich rasch von einer subkulturellen Praxis zur breiten Mode, nachdem prägende Momente der Popkultur — darunter Auftritte von Topmodels auf dem Laufsteg und Musikvideos — es zu einem der bekanntesten und begehrtesten Körperpiercings des Jahrzehnts machten. Heute zählt es durchgehend zu den beliebtesten Körperpiercings außerhalb des Ohrs; eine großangelegte Erhebung aus dem Jahr 2005 führte Bauchnabel-Piercings an der Spitze aller Körperpiercings außerhalb des Ohrläppchens.')
          . $p('Auch die Motivation hinter dem Piercing hat sich verändert. Eine Studie aus dem Jahr 2022 zeigte, dass Bauchnabel-Piercings die körperliche Selbstwahrnehmung deutlich verbessern und dass das Piercing tief und positiv in das eigene Körperbild und Körpererleben eingebunden wird. Für viele Kundinnen und Kunden ist es gleichzeitig ein persönlicher Meilenstein, eine ästhetische Aufwertung und eine Form des Selbstausdrucks.')
        ),

        '5136f61d' => array('text' => 'Terminanfrage', 'link' => array('url' => '/contact/')),

        '14cee6c6' => array('title' => 'Auswirkungen auf die Verschönerung — Welche Probleme es löst und welche Herausforderungen bestehen'),

        '643ed5e6' => array('editor' =>
            $p('Ein professionell ausgeführtes Bauchnabel-Piercing lenkt den Blick auf die Taille, stärkt das Körpergefühl und passt zu einer außerordentlich großen Bandbreite an Stilen und Körperformen — von minimalistischen Bananenstäben bis hin zu aufwendigen Anhängern mit Edelsteinen oder Gold. Es ist eines der wenigen Piercings, das sichtbar getragen oder vollständig unter der Kleidung verborgen werden kann, und eignet sich damit für berufliche wie private Zusammenhänge.')
          . $p('Das Bauchnabel-Piercing ist besonders vorteilhaft für:')
          . '<ul>'
          . $b('Anatomiegerechte Platzierung:', 'Ein professioneller Piercer beurteilt die natürliche Form, die Tiefe der Nabelfalte und die Gewebedicke deines Bauchnabels, bevor die Markierung gesetzt wird, damit der Schmuck genau zu deiner Anatomie passt und keine generische Platzierung erzwungen wird.')
          . $b('Mehr Körperselbstvertrauen:', 'Kundinnen und Kunden berichten häufig, dass ein korrekt platziertes und vollständig verheiltes Bauchnabel-Piercing die Taille definierter und harmonischer wirken lässt.')
          . $b('Stilvielfalt:', 'Von Bananenstäben aus Titan bis zu Ringen aus massivem Gold — die Schmuckauswahl erlaubt es, das Piercing gemeinsam mit dem persönlichen Stil weiterzuentwickeln.')
          . '</ul>'
        ),

        '866a45a' => array('text' => 'Terminanfrage', 'link' => array('url' => '/contact/')),

        'db67cee' => array('title' => 'Häufige Herausforderungen — und warum Technik entscheidend ist:'),

        '5ba317b' => array('editor' =>
            '<ul>'
          . $b('Anatomische Eignung:', 'Nicht jede Nabelform ist unmittelbar geeignet. Nabel, die sich im Sitzen häufig nach innen falten, Nabel mit Narbengewebe nach Operationen oder eine zu geringe Gewebetiefe können ein angepasstes Vorgehen oder eine alternative Platzierung wie ein Floating-Navel-Piercing erfordern.')
          . $b('Heilungsdauer:', 'Da sich der Bauch bewegt, faltet und regelmäßig Kontakt mit Kleidung hat, dauert die Heilung deutlich länger als bei den meisten Piercings — typischerweise sechs bis zwölf Monate bis zur vollständigen inneren Abheilung, nicht nur bis zum äußeren Erscheinungsbild.')
          . $b('Abstoßung und Wanderung des Schmucks:', 'Bei falscher Platzierung, zu flachem Stich oder falscher Schmuckstärke kann der Körper das Piercing im Laufe der Zeit an die Oberfläche drängen. Eine professionelle Platzierung mit korrekter Größenwahl verhindert das.')
          . '</ul>'
          . '<p><b>Infektions- und Narbenrisiko:</b>' . $S . ' Bauchnabel-Piercings neigen besonders zu Entzündungen, wenn die Nachsorge unzureichend ist oder die Hygienestandards des Studios nicht ausreichen. Keloidbildung ist eine bekannte Komplikation bei entsprechend veranlagten Personen.</span></p>'
        ),

        '81a7f71' => array('text' => 'Terminanfrage', 'link' => array('url' => '/contact/')),

        '3df00f6e' => array('title' => 'So löst unser Studio das Problem — Ein medizinisch orientierter Bauchnabel-Piercingprozess'),

        '4bc0a6f2' => array('editor' =>
            $p('Wir behandeln das Bauchnabel-Piercing mit demselben Sorgfaltsniveau, das für jeden hautverletzenden Eingriff gilt. In Deutschland sind Studios, die Eingriffe an der Haut vornehmen, an das Infektionsschutzgesetz (IfSG) und die jeweils geltenden Landeshygienevorschriften gebunden — einschließlich eines dokumentierten Hygieneplans, hygienischer Arbeitsbereiche, korrekter Hand- und Flächendesinfektion sowie steriler Einweginstrumente. Unser Studio erfüllt diese Anforderungen vollständig und versteht diesen rechtlichen Rahmen als Mindeststandard, auf dem wir zusätzliche Best-Practice-Abläufe aufbauen.')
          . '<p>&nbsp;</p>'
        ),

        '16286722' => array('text' => 'Beratungstermin', 'link' => array('url' => '/contact/')),

        '9b2be5f' => array('editor' =>
            '<h5><b>1) Intensive Beratung buchen (Gesundheit + Hygiene)</b></h5>'
          . $p('Jeder Termin für ein Bauchnabel-Piercing beginnt mit einer ausführlichen Einzelberatung. Wir besprechen deine Ziele, beurteilen die Anatomie deines Bauchnabels im Detail und erfassen relevante Vorerkrankungen — darunter frühere Operationen im Bauchbereich, bekannte Metallunverträglichkeiten, Hauterkrankungen, Schwangerschaften oder Besonderheiten des Immunsystems. Wir erklären dir unsere Hygienemaßnahmen klar und transparent: sterile Hohlnadeln zum Einmalgebrauch, hypoallergener Erstschmuck aus implantatgeeignetem Titan oder chirurgischem Stahl, Einwegschutzmaterialien, Entsorgung aller Spitzen in durchstichsicheren Behältern sowie vollständige Hand- und Flächendesinfektion. Piercingpistolen werden in unserem Studio nicht eingesetzt — sie lassen sich nicht vollständig sterilisieren und verursachen bekanntermaßen ein größeres Gewebetrauma und ein höheres Infektionsrisiko.')
          . $p('Auch das Schmerzmanagement wird professionell begleitet. Wir verwenden zugelassene örtliche Betäubungsmittel, und für geeignete Kundinnen und Kunden, die eine zusätzliche Schmerzbehandlung benötigen, kann nach Terminvereinbarung ein Anästhesist im Studio hinzugezogen werden.')
          . '<h5><b>2) Platzierungsdesign (Design + Vorschau)</b></h5>'
          . $p('Bevor eine Nadel vorbereitet wird, markieren wir den genauen Platzierungspunkt an deinem Bauchnabel mit professionellen Messwerkzeugen. Du betrachtest die Markierung im Stehen, im Sitzen und in Bewegung — denn ein Bauchnabel-Piercing muss in jeder Position korrekt sitzen und funktionieren, nicht nur im ruhigen Stand. Wir besprechen die Schmuckstärke (Industriestandard 14G Bananenstab), die Stablänge, die so gewählt wird, dass sie die anfängliche Schwellung aufnimmt, und die Materialqualität deines Erstschmucks. Du gibst die Platzierung frei und bist vollständig informiert, bevor wir fortfahren.')
          . '<h5><b>3) Nachberatung (falls erforderlich)</b></h5>'
          . $p('Wenn ein Folgetermin erforderlich ist — um den Heilungsfortschritt zu prüfen, eine frühe Reizung zu behandeln oder die Schmuckgröße nach dem Abklingen der ersten Schwellung zu beurteilen — vereinbaren wir eine eigene Nachberatung. So stellen wir sicher, dass deine Heilung planmäßig verläuft und dein Piercing vom ersten Tag an bis zum Abschluss der Heilung bewusst gesetzt und gut gepflegt bleibt.')
        ),

        '080767f' => array('text' => 'Beratungstermin', 'link' => array('url' => '/contact/')),
    ),

    /* ==================================================================
     * 453 — /realismus/  (Realismus-Tattoos)
     *
     * Requested 2026-09-01: complete "Schritt 5 — Nachkontrolle".
     *
     * The German text was already there but stopped mid-sentence, at
     * "...Kontrast-Boost sinnvoll ist," with nothing after the comma. The
     * English page (post 2800) carries the finished sentence, so the ending
     * is translated from that rather than invented.
     *
     * Two further defects in the same widget, fixed while rewriting it:
     *   - Schritt 2 had <b> wrapped around the heading AND the whole body,
     *     so the entire paragraph rendered bold.
     *   - Schritt 3's heading was not bold, unlike steps 1, 4 and 5.
     *
     * Markup follows this page's own pattern, taken from the correct
     * Schritt 1 block in widget b005757:
     *     <p><b>Schritt N — Titel</b><b><br></b>Fließtext</p>
     * (This page does not use the <span style="font-weight: 400;"> wrapper
     * that the English pages use — matching it here would change the look.)
     * ================================================================== */
    453 => array(

        '7df3017' => array('editor' =>
            '<p><b>Schritt 2 — Design optimieren</b><b><br></b>'
          . 'Wir passen die Komposition an die Haut an: klarer Fokus, lesbarer Hintergrund und ein Kontrastplan, '
          . 'der das Abheilen übersteht. Wenn die Referenz nicht stark genug ist (unscharf, schlechtes Licht, harte '
          . 'Schatten, falscher Winkel), bitten wir um besseres Material—denn Realismus steht und fällt mit '
          . 'Referenzqualität und Schattierungsplanung.</p>'

          . '<p><b>Schritt 3 — Artist auswählen</b><b><br></b>'
          . 'Realismus ist nicht „one size fits all“. Wir matchen dich mit dem passenden Spezialisten:&nbsp;'
          . '<b>Black and Grey Realism</b>,&nbsp;<b>Color Realism</b>,&nbsp;<b>Portrait Realism</b>, Tiere oder '
          . 'cineastischer Realismus—nach Portfolio-Fit, nicht nur nach Verfügbarkeit.</p>'

          . '<p><b>Schritt 4 — Tattoo machen (Session-Plan + Umsetzung)</b><b><br></b>'
          . 'Wir positionieren das Stencil anatomisch, um Verzerrung zu minimieren, und bauen das Motiv in '
          . 'kontrollierten Layern auf (Struktur → Mitteltöne → dunkle Werte → Textur). Größere Realismus-Projekte '
          . 'teilen wir bei Bedarf in mehrere Sessions, um die Haut zu schützen und Details zu sichern.</p>'

          . '<p><b>Schritt 5 — Nachkontrolle (falls nötig)</b><b><br></b>'
          . 'Nach der Heilung beurteilen wir das Tattoo im echten Leben (nicht nur auf frischen Fotos). Wenn eine '
          . 'kleine Verfeinerung oder ein gezielter Kontrast-Boost sinnvoll ist, planen wir sie strategisch—damit '
          . 'dein Realismus-Tattoo scharf, lesbar und langlebig bleibt.</p>'
        ),
    ),

    /* ==================================================================
     * 454 — /schwarz-grau-realismus/  (Black & Grey Realism, DE)
     *
     * Requested 2026-09-01: strip content that does not belong on the page.
     * Two separate problems, both APPENDED to the end of widgets that also
     * hold genuine copy — which is why the whole widgets cannot simply be
     * deleted, and why each is re-stated here ending exactly where the real
     * content ends:
     *
     *  1. An unfinished editorial note left in live copy, addressed to the
     *     site owner rather than to a visitor:
     *       "Wenn du möchtest, sag mir, welches Motiv du am häufigsten
     *        targetest ... damit sie dafür besser rankt"
     *
     *  2. Five English sponsorship-programme paragraphs pasted onto a German
     *     tattoo-style page ("This structure gives artists room...",
     *     "A structured sponsorship helps...", "Through regular
     *     collaboration...", "Magic Moon also believes...", "If you are an
     *     individual tattoo artist...").
     *
     * Everything kept below is the page's existing markup, unchanged, except
     * for one added full stop noted inline.
     * ================================================================== */
    454 => array(

        // keeps the placement-caution list; drops 2 trailing English paragraphs
        '8f9d007' => array('editor' =>
            '<p><b>Stellen, bei denen man vorsichtig sein sollte (mehr Verzerrung / schnelleres Verblassen):</b></p>'
          . '<ul>'
          . '<li aria-level="1"><b>Rippen, Bauch, Innenseite Oberarm:</b>&nbsp;starke Bewegung, mehr Schmerz, mehr Schwellung, mehr Veränderung über Zeit.</li>'
          . '<li aria-level="1"><b>Hände, Finger, Füße:</b>&nbsp;schnelles Verblassen; Realismus verliert dort Micro-Details besonders schnell.</li>'
          // full stop added — this was the only bullet missing one
          . '<li aria-level="1"><b>Armbeuge / Kniekehle:</b>&nbsp;dauerhaftes Falten kann Verläufe weicher machen und Kontrast reduzieren.</li>'
          . '</ul>'
        ),

        // keeps Schritt 3-5; drops the editorial note and 3 English paragraphs
        'f3dd737' => array('editor' =>
            '<p><b>Schritt 3: Artist auswählen (Spezialist-Matching)</b><b><br></b>'
          . 'Black-and-Grey Realism ist kein einzelner Skill. Portraits, Tiere, religiöser Realismus und surrealer '
          . 'Grey-Realism brauchen unterschiedliche Stärken. Wir matchen dich mit dem Artist, dessen&nbsp;'
          . '<b>abgeheiltes Portfolio</b> am besten zu deinem Motiv und deinem gewünschten Finish passt.</p>'

          . '<p><b>Schritt 4: Tattoo-Session (Technik + Qualitätskontrolle)</b><b><br></b>'
          . 'Am Tag der Session achten wir auf sauberen Stencil-Flow, gleichmäßige Sättigung und kontrolliertes '
          . 'Greywash-Layering. Kontrast wird intelligent aufgebaut, ohne die Haut zu überarbeiten—für glatte '
          . 'Verläufe und stabile Schwarztöne als Basis eines langlebigen Black-and-Grey-Realism-Tattoos.</p>'

          . '<p><b>Schritt 5: Folgetermin (falls nötig) + Langzeit-Guidance</b><b><br></b>'
          . 'Wenn nach dem Heilen eine kleine Optimierung sinnvoll ist (bei großen Realismus-Arbeiten normal), '
          . 'planen wir eine Kontrolle/Tattoo-Nacharbeit. Du bekommst außerdem klare Aftercare- und '
          . 'Sonnenschutz-Guidelines—denn Realismus bleibt scharf, wenn er geschützt wird.</p>'
        ),
    ),

    /* ==================================================================
     * 2813 — /en/black-grey-realism-en/  (Black & Grey Realism, EN)
     *
     * Same editorial note, English wording, appended after Step 5. Verified
     * that this page carries NONE of the sponsorship paragraphs, so this is
     * the only cut needed here.
     * ================================================================== */
    2813 => array(

        'd936f2a' => array('editor' =>
            '<p><b>Step 3: Choose the Artist (Specialist Matching)</b><b><br></b>'
          . 'Black and grey realism isn’t one single skill. Portrait realism, animals, religious realism, and '
          . 'surreal grayscale all demand different strengths. We match you with the artist whose&nbsp;'
          . '<b>healed portfolio</b>&nbsp;best fits your subject and the finish you want '
          . '(soft realism vs high-contrast realism).</p>'

          . '<p><b>Step 4: Tattoo Making (Technique + Quality Control)</b><b><br></b>'
          . 'On the day, we focus on clean stencil flow, consistent saturation, and controlled greywash layering. '
          . 'We build contrast in smart stages to avoid overworking the skin—keeping gradients smooth and blacks '
          . 'solid, which is the foundation of a long-lasting black and grey realism tattoo.</p>'

          . '<p><b>Step 5: Reconsultation (If Needed) + Long-Term Guidance</b><b><br></b>'
          . 'If your piece needs a small refinement after healing (common with large realism), we schedule a '
          . 'recheck/touch-up. You’ll also receive clear aftercare and sun-protection guidance—because realism '
          . 'stays crisp when it’s protected.</p>'
        ),
    ),

    /* ==================================================================
     * 543 — /floral/  (Floral-Tattoos, DE)
     *
     * Requested 2026-09-03. The German page was a placeholder: Lorem Ipsum
     * bodies under sponsorship-programme headings. The English page
     * /en/floral-en/ (post 3448) holds the finished article; page-clone
     * copies its layout onto 543 and this block translates all 15 widgets.
     * Widget ids are the English page's (they arrive with the clone).
     *
     * Headings follow the German tattoo-style pages already on the site
     * (/realismus/, /polynesian-maori/): "Unser Service", "Geschichte der …",
     * "Wo ist die beste Stelle für ein …-Tattoo?", "Wie unser Studio das löst:
     * der Magic Moon …-Prozess (5 Schritte)", steps as "Schritt N: …",
     * buttons "Terminanfrage" -> /contact/. Markup mirrors the English
     * widgets exactly (same <p>/<span>/<b>/<ul> structure, same h4 steps).
     * ================================================================== */
    543 => array(

        '22e4b19e' => array('title' => 'Unser Service'),
        '430ce95e' => array('title' => 'Floral'),
        '243e30e9' => array('title' => 'Geschichte der Floral-Tattoos'),

        '15ae1c4a' => array('editor' =>
            '<p>' . $S . 'Floral-Tattoos (auch </span><b>Blumen-Tattoos</b>' . $S . ' oder </span><b>botanische Tattoos</b>' . $S . ' genannt) gehören zu den ältesten „immergrünen“ Motiven der Körperkunst, weil Pflanzen von Natur aus Bedeutung tragen: Liebe, Trauer, Wiedergeburt, Stärke, Jahreszeiten und Identität. Der Kunsthistoriker Dr. Matt Lodder weist darauf hin, dass botanische Bildwelten in allen Kulturen und Epochen vorkommen und Pflanzen in den Traditionen der Körperkunst sowohl Inspiration als auch Rohmaterial geliefert haben.&nbsp;</span></p>'
          . '<p>' . $S . 'Im Westen wurden Blumen vor allem durch das </span><b>American Traditional</b>' . $S . ' Tätowieren der frühen bis mittleren 1900er Jahre zur Ikone. Artists wie Norman „Sailor Jerry“ Collins machten kräftige, gut lesbare Designs populär – Rosen eingeschlossen –, die auf arbeitender Haut Bestand haben (dicke Outlines, satte Farbflächen, hoher Kontrast).&nbsp;</span></p>'
          . '<p>' . $S . 'In Japan spielen Blumen auch im </span><b>Irezumi</b>' . $S . ' eine große Rolle: Dort dienen sie oft der kulturellen Symbolik und tragen den Fluss großer Kompositionen über den Körper. Kirschblüten (Sakura) stehen zum Beispiel für die Schönheit und Vergänglichkeit des Lebens – ideal, um mit einem Tattoo eine Geschichte zu erzählen.</span></p>'
        ),

        '63ad9417' => array('text' => 'Terminanfrage', 'link' => array('url' => '/contact/')),

        'fac2c38' => array('title' => 'Wo ist die beste Stelle für ein Floral-Tattoo?'),

        '67328a9f' => array('editor' =>
            '<p>' . $S . 'Die „beste“ Platzierung hängt von </span><b>Größe + Detailgrad + Stil</b>' . $S . ' ab (Fine-Line-Floral vs. kräftige Traditional-Rose vs. realistische botanische Schattierung). Wenn dein Floral-Tattoo gut altern soll, bevorzuge Bereiche mit </span><b>stabiler Haut</b>' . $S . ', wenig Reibung und wenig Sonne – so bleiben Linien länger scharf und Verläufe weicher.&nbsp;</span></p>'
          . '<p>' . $S . 'Platzierungen, die für Floral-Tattoo-Designs besonders gut funktionieren:</span></p>'
          . '<ul>'
          . $LI . '<b>Außenseite Oberarm / Schulterkappe:</b>' . $S . ' große Fläche, gute Haltbarkeit, leicht zu einem Sleeve erweiterbar.</span></li>'
          . $LI . '<b>Unterarm (außen):</b>' . $S . ' hohe Sichtbarkeit, starke „Stiel-und-Fluss“-Formen für Rosen, Pfingstrosen und Ranken.</span></li>'
          . $LI . '<b>Oberer Rücken / Schulterblatt:</b>' . $S . ' ideal für größere botanische Kompositionen mit feinen Details.</span></li>'
          . $LI . '<b>Oberschenkel:</b>' . $S . ' hervorragend für große Floral-Motive (Pfingstrose, Chrysantheme, botanisches Bouquet) mit Platz für weiche Schattierung.&nbsp;</span></li>'
          . '</ul>'
          . '<p>' . $S . 'Platzierungen, die </span><i>' . $S . 'zwar</span></i>' . $S . ' großartig aussehen können, aber klügere Designentscheidungen verlangen: Rippen, Hände, Füße sowie Zonen mit starker Reibung oder häufiger Dehnung – dort verschwimmen Details schneller, deshalb empfehlen wir hier kräftigere Linien und einfachere Mikro-Details.&nbsp;</span></p>'
        ),

        '10a070e2' => array('text' => 'Terminanfrage', 'link' => array('url' => '/contact/')),

        '2bab5177' => array('title' => 'Welche Herausforderungen gibt es bei Floral-Tattoos?'),

        'f6fc1f1' => array('editor' =>
            '<p>' . $S . 'Floral-Tattoos wirken „einfach“, sind technisch aber anspruchsvoll. Die wichtigsten Herausforderungen:</span></p>'
          . '<p><b>Realismus mit vielen Details:</b>' . $S . ' Blütenblätter, Schichtungen und weiche Verläufe können matschig werden, wenn das Design zu klein ist oder der Kontrast nicht richtig geplant wurde (besonders bei Fine-Line-Floral-Tattoos).</span></p>'
          . '<p><b>Alterung und Verblassen:</b>' . $S . ' Sonne ist einer der größten Faktoren für das Verblassen von Tinte; Dermatologen empfehlen, Tattoos nach der Heilung mit Breitband-Sonnenschutz LSF 30+ zu schützen.&nbsp;</span></p>'
          . '<p><b>Haut + Immunreaktion:</b>' . $S . ' Tattoofarbe sitzt in der Dermis, und das Immunsystem beeinflusst, wie die Farbe gehalten wird und sich mit der Zeit verändert – ein Grund, warum scharfe Mikro-Details über die Jahre weicher werden können.&nbsp;</span></p>'
          . '<p><b>Heilungsrisiken:</b>' . $S . ' Mangelhafte Nachsorge kann zu Reizungen, Problemen mit Krusten, Texturveränderungen oder sogar Infektionen führen – und jedes davon kann die Klarheit deines Floral-Tattoos beeinträchtigen.&nbsp;</span></p>'
        ),

        '55d06f48' => array('text' => 'Terminanfrage', 'link' => array('url' => '/contact/')),

        '21521b86' => array('title' => 'Wie unser Studio das löst: der Magic Moon Floral-Tattoo-Prozess (5 Schritte)'),

        '7b0cb73' => array('editor' =>
            '<p>' . $S . 'Wir „kopieren“ nicht einfach eine Blume. Wir entwerfen ein </span><b>individuelles Floral-Tattoo</b>' . $S . ', das zu deinem Körper, deiner Haut und zum Altern von Tattoos passt.</span></p>'
          . '<h4><b>Schritt 1: Beratung buchen</b></h4>'
          . '<p>' . $S . 'Wir starten mit einer gezielten Tattoo-Beratung: Stilrichtung (botanisch, Traditional, Fine-Line, Black &amp; Grey, Farbe), Platzierung, Größe und Referenzen. Wir sprechen auch über Haltbarkeit – welche Details an deiner gewählten Stelle am besten bestehen bleiben.&nbsp;</span></p>'
          . '<h4><b>Schritt 2: Design optimieren</b></h4>'
          . '<p>' . $S . 'Unsere Artists verfeinern die Schablone mit </span><b>Kontrastplanung</b>' . $S . ' (der Schlüssel zu lesbaren Blütenblättern), einer Strategie für Linienstärken und Abständen, die natürliches Altern erlauben, ohne dass die Blütenform verloren geht.</span></p>'
          . '<p>&nbsp;</p>'
        ),

        '1ca1283' => array('editor' =>
            '<h4><b>Schritt 3: Artist auswählen</b></h4>'
          . '<p>' . $S . 'Floral-Arbeiten sind nicht „one size fits all“. Wir matchen dich mit dem passenden Spezialisten: weiche Black-and-Grey-Schattierung, satter Farbrealismus oder kräftige Traditional-Rosen – damit die Technik zum Designziel passt.</span></p>'
          . '<h4><b>Schritt 4: Tattoo-Session</b></h4>'
          . '<p>' . $S . 'Am Tattoo-Tag setzen wir die Schablone entlang des Muskelverlaufs (damit sich das Floral-Motiv natürlich „bewegt“) und arbeiten mit kontrollierter Tiefe und sauberer Sättigung. Genau hier schützt professionelle Technik die Details und reduziert Gewebetrauma, das Kanten später verschwimmen lassen könnte.</span></p>'
          . '<h4><b>Schritt 5: Nachkontrolle (falls nötig)</b></h4>'
          . '<p>' . $S . 'Wenn dein Floral-Tattoo eine Verfeinerung braucht, vereinbaren wir nach der Heilung eine Nachkontrolle. Außerdem bekommst du eine Nachsorgeanleitung für eine saubere Heilung und langfristige Leuchtkraft – inklusive Sonnenschutz-Gewohnheiten nach dem Abheilen.&nbsp;</span></p>'
        ),

        '5eebdc67' => array('text' => 'Terminanfrage', 'link' => array('url' => '/contact/')),
    ),

    /* ==================================================================
     * 544 — /botanisch/  (page content is the Biomechanic / biotech style)
     *
     * Requested 2026-09-03: the placement section (heading 5dd64f0e + text
     * 10a8c92e) was the only block still in English on an otherwise German
     * page. Translated here; markup mirrors the existing block exactly
     * (<p>/<b>/&nbsp;/<ul><li aria-level="1">). Heading follows the house
     * pattern "Wo ist die beste Stelle für ein …-Tattoo?".
     * ================================================================== */
    544 => array(

        '5dd64f0e' => array('title' => 'Wo ist die beste Stelle für ein biotechnisches Tattoo?'),

        '10a8c92e' => array('editor' =>
            '<p>Weil&nbsp;<b>biotechnische / biomechanische Tattoos</b>&nbsp;von Tiefe, Perspektive und anatomischer Bewegung leben, sind die besten Platzierungen meist&nbsp;<b>größere Flächen mit starken Konturen</b>. Dort kann sich das Design natürlich um den Körper legen, sodass die Illusion integriert wirkt – als gehöre sie unter deine Haut.</p>'
          . '<p>Arme und Beine sind besonders beliebt, weil Muskelgruppen und Gelenke eine natürliche Krümmung für mechanische Formen, geschichtete Schatten und realistische „aufgerissene Haut“-Effekte bieten.</p>'
          . '<p>Unsere Top-Platzierungen für&nbsp;<b>Biotech- / Biomech-Tattoos</b>:</p>'
          . '<ul>'
          . '<li aria-level="1"><b>Kompletter Sleeve (Oberarm → Unterarm):</b>&nbsp;ideal für durchgehendes „Innenstruktur“-Storytelling und 3D-Übergänge.</li>'
          . '<li aria-level="1"><b>Wade + Schienbein / ganzes Bein:</b>&nbsp;lange vertikale Fläche für Kabel, Kolben und Biomech-Panzerung.</li>'
          . '<li aria-level="1"><b>Brust + Schulterkappe:</b>&nbsp;starke Krümmung für „Rippe-trifft-Maschine“-Konzepte.</li>'
          . '<li aria-level="1"><b>Oberer Rücken:</b>&nbsp;viel Platz für großflächige Biomech-„Framework“-Kompositionen.</li>'
          . '</ul>'
          . '<p>Die Platzierung ist nicht nur eine Frage der Ästhetik – sie beeinflusst auch,&nbsp;<b>wie das Tattoo altert</b>, wie gut die Details über die Zeit lesbar bleiben und wie gut das Design im Fluss bleibt, wenn sich dein Körper bewegt.</p>'
        ),
    ),

    /* ==================================================================
     * 3568 — /en/chicano-en/  (English)
     * After the DE<->EN layout swap (page-clone), the English article's
     * first heading still read "Unser Service". English copy for that one
     * widget; everything else on the page is already English.
     * ================================================================== */
    3568 => array(
        '694efba7' => array('title' => 'Our Services'),
    ),
);
