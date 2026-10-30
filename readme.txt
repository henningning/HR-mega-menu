=== Schlichtes Megamenü Block ===
Contributors: Henning Rolapp / OpenAI
Requires at least: 6.5
Requires PHP: 7.4
Stable tag: 5.7.0
License: GPLv2 or later

Erweitert native WordPress-Menüs um Mega-Menüs, Dropdowns, freie Spalten, automatische Inhaltslisten und einen Gutenberg-Ausgabeblock.

== Changelog ==

= 5.7.0 =
* Wählbare horizontale oder vertikale Ausrichtung pro Menü.
* Vertikale Untermenüs klappen innerhalb der Menübreite auf; mobile Darstellung bleibt erhalten.

= 5.6.0 =
* „Mehr anzeigen“-Link automatischer Inhaltslisten erhält frei wählbare Zielarten: automatisch, Taxonomie, übergeordneter Inhalt, Inhaltstyp-Archiv, bestimmter Inhalt oder eigene URL.
* Bei „bestimmter Inhalt“ werden aktuelle Permalinks dynamisch verwendet.

= 5.5.0 =
* Frontend-Reihenfolge nativer Menüeinträge wird nun auf jeder Hierarchieebene ausdrücklich nach WordPress `menu_order` aufgebaut.
* Stabile Reihenfolge auch nach Filtern oder Erweiterungen von `wp_get_nav_menu_items()`.

= 5.3.0 =
* Verlinkte Überschriften automatischer Inhaltslisten zeigen nun den normalen Hand-Cursor.
* Automatische Inhaltslisten: vier Überschriften-Modi (keine, freier Text, verknüpfter Inhalt, übergeordneter Inhalt).
* Freie Überschriften können optional auf Seiten, Beiträge oder Informationen verlinken.
* Verknüpfte Überschriften übernehmen Titel und Permalink dynamisch.

= 5.1.0 =
* Menüstruktur vollständig auf native WordPress-Menüs umgestellt.
* Eigene Drag-&-Drop-Implementierung entfernt.
* Dropdown/Megamenü-Typ als Metadaten an nativen Menüeinträgen.
* Freie Megamenü-Spalten als native verschiebbare Menüelemente.
* Automatische Inhaltslisten mit Inhaltstyp, Taxonomie, Übergeordneten, Sortierung, Reihenfolge und „Mehr anzeigen“.
* Migration vorhandener 4.x-Menüs.
* Gutenberg-Block auf Auswahl nativer WordPress-Menüs vereinfacht.


## Version 5.5 – Reihenfolge

Die Frontend-Ausgabe liest die tatsächlich in der Datenbank gespeicherten Positionen (`nav_menu_item.menu_order`) direkt aus dem nativen WordPress-Menü. Dadurch können Filter oder die interne Neunummerierung von `wp_get_nav_menu_items()` die Reihenfolge nicht mehr verfälschen. Automatische Inhaltslisten werden außerdem an ihrer tatsächlichen Position zwischen normalen Megamenü-Spalten ausgegeben und nicht mehr gesammelt an das Ende verschoben.
