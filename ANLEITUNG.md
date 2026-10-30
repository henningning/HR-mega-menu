# Schlichtes Megamenü Block 5.0

Version 5.1 stellt die Menüstruktur vollständig auf **native WordPress-Menüs** um. Das Plugin besitzt keine eigene Drag-&-Drop-Implementierung mehr.

## Grundprinzip

- **Megamenü → Menüstruktur**: Auswahl vorhandener WordPress-Menüs und direkter Einstieg in den nativen Menüeditor.
- **WordPress-Menüeditor**: Reihenfolge, Einrückung, Untermenüs und Verschieben mit WordPress' eigener Menüverwaltung.
- **Megamenü → Design & Verhalten**: Schrift, Farben, Größen, Breiten, Radius, Dropdown-Design, Mobil-Overlay und Hover/Klick-Verhalten.
- **Gutenberg-Block „Schlichtes Megamenü“**: wählt nur ein natives WordPress-Menü aus.

## Hauptmenüpunkt: Dropdown oder Megamenü

Jeder normale WordPress-Menüeintrag erhält im aufgeklappten Menüelement das Zusatzfeld **Schlichtes Megamenü**:

- Kein Untermenü
- Normales Dropdown
- Megamenü

Die Einstellung wirkt, wenn der Eintrag auf der obersten Menüebene liegt.

## Megamenü-Spalten

Links im nativen WordPress-Menüeditor erscheint die Box **Megamenü-Elemente**.

Dort kann eine **Megamenü-Spalte** mit freier Überschrift angelegt werden. Die Spalte erscheint anschließend als normal verschiebbarer WordPress-Menüeintrag. Ziehe sie unter einen Hauptmenüpunkt vom Typ „Megamenü“. Normale Seiten, Beiträge, Informationen oder Links werden unter die Spalte eingerückt.

Die Spaltenüberschrift ist unabhängig von einer Seite oder einem Beitrag.

## Automatische Inhaltslisten

Über **Megamenü-Elemente → Automatische Inhaltsliste** wird ein spezieller Menüeintrag erzeugt. Er kann per WordPress-Drag-&-Drop unter ein Megamenü gezogen werden.

Einstellungen:

- Überschrift
- Inhaltstyp (Beiträge, Informationen und weitere geeignete öffentliche Inhaltstypen)
- Taxonomie / Kategorie
- Begriff
- Übergeordneter Inhalt bei hierarchischen Inhaltstypen
- Sortieren nach: Datum, Titel, Menü-Reihenfolge oder Änderung
- Reihenfolge: aufsteigend / absteigend
- maximale Anzahl
- Datum anzeigen
- „Mehr anzeigen“-Link
- Linktext
- automatisches Ziel oder eigene URL

Das automatische Ziel verwendet in dieser Reihenfolge:

1. gewählte Taxonomie-/Kategorie-Seite,
2. gewählten übergeordneten Inhalt,
3. Archiv des Inhaltstyps.

Der Link erscheint nur, wenn mehr Inhalte vorhanden sind als maximal angezeigt werden.

## Inhaltstyp „Information“

Das Plugin stellt sicher, dass `leitstelle_info` für WordPress-Menüs verfügbar ist. Dadurch kann „Information“ im nativen Menüeditor wie Seite oder Beitrag hinzugefügt werden.

## Titel und URLs

Normale WordPress-Menüeinträge verwenden die native WordPress-Logik. Wird bei einem verknüpften Inhalt keine eigene „Angezeigte Bezeichnung“ gesetzt, folgt der Menüeintrag dem aktuellen Titel des Inhalts. Permalinks werden ebenfalls von WordPress aufgelöst.

## Migration aus Version 4.x

Unter **Megamenü → Design & Verhalten** werden vorhandene 4.x-Menüs angeboten. Mit **In natives WordPress-Menü umwandeln** wird eine Kopie in ein echtes WordPress-Menü überführt. Das alte Menü bleibt zur Sicherheit bestehen.

Dabei werden soweit möglich übernommen:

- Reihenfolge und Hierarchie
- Dropdown-/Megamenü-Typen
- freie Spalten
- automatische Inhaltslisten
- Design- und Verhaltenseinstellungen

## Mobile Navigation

Das Fullscreen-Overlay aus den bisherigen Versionen bleibt erhalten. Der Hintergrund wird bei geöffnetem mobilen Menü gesperrt; Untermenüs werden als Accordion bedient.

## Version 5.3 – Überschriften automatischer Inhaltslisten

Für jede automatische Inhaltsliste gibt es vier Modi für die sichtbare Überschrift:

1. **Keine sichtbare Überschrift** – die Liste beginnt direkt mit den automatisch gefundenen Inhalten.
2. **Freier Text** – eine eigene Überschrift. Optional kann sie auf eine veröffentlichte Seite, einen Beitrag oder eine Information verlinkt werden.
3. **Verknüpfter Inhalt** – Titel und Permalink einer ausgewählten Seite, eines Beitrags oder einer Information werden automatisch als klickbare Überschrift verwendet. Änderungen am Titel oder Permalink werden automatisch übernommen.
4. **Übergeordneten Inhalt verwenden** – der unter „Übergeordneter Inhalt“ gewählte Eintrag liefert automatisch Titel und Link der Überschrift. Dieser Modus eignet sich besonders für hierarchische Inhaltslisten.

Beispiel:

- Megamenü-Spalte: **Gebäude**
- Automatische Inhaltsliste 1: Übergeordnet = **Erdgeschoss**, Überschrift = **Übergeordneten Inhalt verwenden**
- Automatische Inhaltsliste 2: Übergeordnet = **Obergeschoss**, Überschrift = **Übergeordneten Inhalt verwenden**

Im Frontend erscheinen dann „Erdgeschoss“ und „Obergeschoss“ als klickbare Überschriften; darunter folgen die jeweils automatisch ermittelten Inhalte.


## Version 5.5 – Menü-Reihenfolge

Die Ausgabe übernimmt die in der nativen WordPress-Menüstruktur gespeicherte Reihenfolge nun auf jeder Ebene ausdrücklich über `menu_order`. Damit entspricht die Reihenfolge im Frontend der Reihenfolge unter **Design → Menüs** bzw. **Megamenü → Menüstruktur**.


## Version 5.5 – Reihenfolge

Die Frontend-Ausgabe liest die tatsächlich in der Datenbank gespeicherten Positionen (`nav_menu_item.menu_order`) direkt aus dem nativen WordPress-Menü. Dadurch können Filter oder die interne Neunummerierung von `wp_get_nav_menu_items()` die Reihenfolge nicht mehr verfälschen. Automatische Inhaltslisten werden außerdem an ihrer tatsächlichen Position zwischen normalen Megamenü-Spalten ausgegeben und nicht mehr gesammelt an das Ende verschoben.

## „Mehr anzeigen“-Ziel

Für automatische Inhaltslisten kann das Ziel des „Mehr anzeigen“-Links separat gewählt werden:

- automatisch passend zur Liste,
- gewählte Kategorie / Taxonomie,
- gewählter übergeordneter Inhalt,
- Archiv des Inhaltstyps,
- bestimmte Seite / bestimmter Beitrag / bestimmte Information,
- eigene URL.

Bei einem konkret ausgewählten Inhalt wird dessen aktueller Permalink verwendet.


## Version 5.7: Vertikales Menü

Unter **Megamenü → Design & Verhalten → Menüzeile → Ausrichtung**
**Vertikal – Untermenüs darunter aufklappen** auswählen und speichern.
Der Gutenberg-Block zeigt das ausgewählte Menü mit dieser Ausrichtung an.
Die Einstellung gilt für alle Blöcke, die dasselbe Menü verwenden.
Für unterschiedliche Ausrichtungen zwei separate WordPress-Menüs verwenden.

Die Hauptmenüpunkte stehen untereinander. Dropdowns, Megamenü-Spalten und
automatische Inhaltslisten öffnen innerhalb der Menübreite direkt unter
dem zugehörigen Menüpunkt. Megamenü-Spalten stehen dabei untereinander.
Die Breite bestimmt die umgebende Gruppe oder Spalte im Blockeditor;
die horizontalen Panel-Breiten werden im vertikalen Modus nicht verwendet.

Für Seitenleisten empfiehlt sich unter **Verhalten** die Einstellung
**Klick** und als Klickziel **Pfeil**: Der Titel bleibt ein normaler Link,
der Pfeil öffnet und schließt das Untermenü. Auch Hover bleibt wählbar.
Auf mobilen Geräten gelten weiterhin die Einstellungen unter **Mobil**
(einschließlich des optionalen Vollbild-Overlays).
Bestehende Menüs bleiben standardmäßig horizontal.
