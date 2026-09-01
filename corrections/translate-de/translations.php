<?php
/**
 * German page copy — corrections/translate-de
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
);
