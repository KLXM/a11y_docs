# **REDAXO Barrierefreiheits-Cheatsheet für Redakteur:innen**

## Sehenswert So wird ein Screenreader verwendet:

[Video auf YouTube](https://www.youtube.com/watch?v=lC6VO3ai8Bg)


## Kurz und knapp
---

---

## **5-Minuten-Schnellcheck vor jeder Veröffentlichung**

**✅ Bevor Sie "Veröffentlichen" klicken:**

1. **Alt-Texte da?** → Haben alle Bilder einen Alt-Text oder alt="" bei dekorativen Bildern?
2. **Links verständlich?** → Kann man am Linktext erkennen, wohin er führt?
3. **Überschriften logisch?** → H1 → H2 → H3 (keine Sprünge)?
4. **Kontrast gut?** → Ist der Text auch bei schlechtem Bildschirm lesbar?
5. **For_sa11y geprüft?** → Zeigt der blaue Button unten rechts grünes Licht?

**⏱️ Das dauert wirklich nur 5 Minuten und verhindert 90% aller Probleme!**

---

| **Kategorie**                  | **Aktion**                                                                                             | **Schritte / Hinweise**                                                                                                    |
|--------------------------------|--------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------|
| **Struktur und Semantik**       | **Achten Sie bei der Verwendung der vorbereiteten Inhaltsblöcke auf Barrierefreiheitshinweise**        | Ihr REDAXO bietet vorbereitete Inhaltsblöcke. Achten Sie darauf, dass diese Blöcke korrekt verwendet werden und entsprechende Hinweise zur Barrierefreiheit (z.B. Alternativtexte für Bilder, richtige Überschriftenhierarchie) beachtet werden. |
| **Bilder und Alternativtexte**  | **Alt-Texte für Bilder einfügen**                                                                      | 1. Im Medienpool Alt-Texte definieren.<br>2. Alternativtexte in Modulen wie "Text mit Bild" einfügen.                       |
|                                | **Dekorative Bilder ohne Alt-Text**                                                                    | Setzen Sie für rein dekorative Bilder das Alt-Attribut auf `alt=""`.                                                       |
| **Links und Navigation**        | **Sinnvolle Linktexte verwenden**                                                                      | Vermeiden Sie generische Linktexte wie "Hier klicken". Der Linktext sollte das Ziel klar beschreiben.                       |
|                                | **Sprunglinks hinzufügen**                                                                             | Fügen Sie **Skiplinks** hinzu, damit Nutzer direkt zum Hauptinhalt springen können.                                         |
| **Farben und Kontraste**        | **Hoher Kontrast für Texte**                                                                           | Stellen Sie sicher, dass Text und Hintergrund einen Kontrastwert von mindestens 4.5:1 haben. Verwenden Sie Tools wie WebAIM. |
|                                | **Text und Hintergrundfarben zugänglich gestalten**                                                     | Farben im Modul klar definieren, kontrastreiche Kombinationen verwenden.                                                   |
| **Medieninhalte**               | **Untertitel für Videos hinzufügen**                                                                   | Bei eingebetteten Videos, falls möglich, Untertitel hinzufügen oder separate Transkripte bereitstellen.                    |
| **Tastaturzugänglichkeit**      | **Tab-Reihenfolge prüfen**                                                                             | Überprüfen Sie, dass die Tab-Reihenfolge auf der Seite logisch ist und interaktive Elemente erreichbar sind.                |
|                                | **Fokus-Indikator nicht entfernen**                                                                    | Sichtbare Fokus-Stile sicherstellen, um Tastaturnutzern die Navigation zu erleichtern.                                      |
| **Testen und Überprüfen**       | **Überprüfen Sie Ihre Inhalte im Frontend mit dem Barrierechecker for_sa11y**                          | 1. Öffnen Sie das REDAXO-Frontend.<br>2. Unten rechts erscheint ein blauer Button mit einer Figur in der Mitte.<br>3. Klicken Sie auf diesen Button, um den **for_sa11y**-Checker zu öffnen.<br>4. for_sa11y überprüft die Seite auf Barrierefreiheitsprobleme und hebt diese direkt hervor. |
|                                | **BFSG-Check mit KLXM**                                                                                | Nutzen Sie den **[BFSG Check der KLXM](https://klxm.de/bfsgcheck/)** für eine umfassende Überprüfung Ihrer Website auf BFSG-Konformität.<br>**Hinweis:** Dies ist nur eine Hilfestellung und bedarf zusätzlich einer rechtlichen und technischen Prüfung durch Fachkräfte. |
|                                | **Barrierefreiheitserklärung erstellen**                                                               | Verwenden Sie den **[Generator für Barrierefreiheitserklärung](https://klxm.de/bfsg-generator/)** als Grundlage für Ihre Erklärung.<br>**Wichtiger Hinweis:** Der Generator bietet nur eine Hilfestellung. Die erstellte Erklärung muss rechtlich und technisch geprüft und an Ihre spezifischen Gegebenheiten angepasst werden. |
|                                | **Tastatursteuerung testen**                                                                            | 1. Navigieren Sie durch die Seite nur mit der Tastatur (Tabulator-Taste für Fokus und Enter für Aktionen).<br>2. Stellen Sie sicher, dass alle interaktiven Elemente (Links, Buttons, Formulare) fokussierbar sind und in logischer Reihenfolge erreicht werden.<br>3. Überprüfen Sie, ob der Fokus-Stil sichtbar und eindeutig ist. |
|                                | **Manuelle Tests durchführen**                                                                         | Testen Sie Seiten mit Screenreadern (z.B. NVDA, VoiceOver) und nur mit der Tastatur, um die Barrierefreiheit sicherzustellen. |
|                                | **Automatisierte Tests kombinieren**                                                                   | Verwenden Sie for_sa11y zusammen mit anderen Tools wie WAVE oder Lighthouse für eine umfassende Prüfung.                   |
| **Formulare**                   | **Eingabefelder richtig beschriften**                                                                  | Jedes Eingabefeld braucht eine klare Beschreibung. In REDAXO: Nutzen Sie die Label-Felder in Formular-Modulen.             |
|                                | **Pflichtfelder kennzeichnen**                                                                         | Markieren Sie Pflichtfelder mit einem Sternchen (*) und erklären Sie am Anfang des Formulars: "Felder mit * sind Pflichtfelder". |
|                                | **Verständliche Fehlermeldungen**                                                                      | Schreiben Sie konkret, was falsch ist: Statt "Fehler" → "Bitte geben Sie Ihre E-Mail-Adresse ein".                        |
| **Überschriften und Listen**    | **Überschriften in der richtigen Reihenfolge**                                                         | H1 nur einmal pro Seite (meist der Seitentitel), dann H2, H3, H4... Keine Überschriftsebenen überspringen.                |
|                                | **Echte Listen verwenden**                                                                             | Für Aufzählungen die Listen-Funktion in REDAXO nutzen, nicht manuell Gedankenstriche oder Punkte tippen.                   |
| **Häufige Anfängerfehler**      | **"Hier klicken" vermeiden**                                                                           | Statt "Hier klicken" schreiben Sie "Laden Sie unseren Flyer herunter" oder "Zur Anmeldung".                               |
|                                | **Farbige Texte richtig einsetzen**                                                                    | Verwenden Sie Farbe nie als einzige Information. Beispiel: Nicht nur rot für Fehler, sondern auch ein Warnsymbol.          |
|                                | **Zu kleine Schrift vermeiden**                                                                        | Nutzen Sie die Standard-Schriftgrößen in REDAXO. Machen Sie Text nicht kleiner als "normal".                               |
| **Barrierefreie PDFs**          | **Barrierefreie PDFs mit Word und LibreOffice erstellen und prüfen**                                   |                                                                                                                            |
|                                | **📄 Umfassende Anleitung für Microsoft Word**                                                          | **[Detaillierte Schritt-für-Schritt-Anleitung: Barrierefreie PDFs mit Microsoft Word](https://data.klxm.de/index.php/s/bFm4t66C8swfKjP)**<br>Diese Anleitung behandelt: Dokumentstrukturierung, Formatierung, Bilder und Tabellen zugänglich machen, Export-Einstellungen und PDF-Prüfung. |
|                                | **Microsoft Word (Windows/macOS) - Kurzübersicht**                                                     | 1. **Strukturierung des Dokuments**: Verwenden Sie korrekt formatierte Überschriften (H1, H2, H3), Listen und Tabellen.<br>2. **Alternativtexte**: Fügen Sie Alternativtexte für alle Bilder und Grafiken ein (Rechtsklick auf Bild > "Bild formatieren" > "Alternativtext").<br>3. **Barrierefreiheitsprüfung**: Nutzen Sie die integrierte Barrierefreiheitsprüfung (Reiter "Überprüfen" > "Barrierefreiheit überprüfen"), um Fehler zu finden und zu beheben.<br>4. **PDF exportieren**: Beim Speichern als PDF aktivieren Sie die Option "Barrierefreie PDF erstellen" (Datei > "Speichern unter" > "PDF" > Optionen > "Dokumentstrukturtags für Barrierefreiheit verwenden"). |
|                                | **LibreOffice (Windows/macOS/Linux)**                                                                  | 1. **Strukturierung des Dokuments**: Verwenden Sie die integrierten Formatvorlagen für Überschriften, Listen und Tabellen.<br>2. **Alternativtexte**: Fügen Sie Alternativtexte für Bilder ein (Rechtsklick auf Bild > "Eigenschaften" > "Alternativtext").<br>3. **PDF exportieren**: Exportieren Sie das Dokument als PDF und aktivieren Sie "PDF/A-1a" oder "PDF/A-2a" für Barrierefreiheit (Datei > "Exportieren als" > "PDF" > PDF/A-1a aktivieren). |
| **PDFs auf Barrierefreiheit prüfen** | **Windows**: Nutzen Sie **PDF Accessibility Checker (PAC 3)** (kostenlos).<br>**macOS**: Verwenden Sie **Adobe Acrobat Pro** oder Online-Tools.<br>**Web**: Verwenden Sie **Online-Dienste** wie das **PAVE-Tool** (https://pave-pdf.org).                                                                                                                                                                                                                                           |
|                                | **PDFs in REDAXO einbinden**                                                                            | 1. Laden Sie das PDF in den **Medienpool** hoch.<br>2. Stellen Sie sicher, dass der Dateiname und der Linktext das Dokument klar beschreiben (z.B. „Barrierefreiheit-Richtlinien.pdf"). |

---

## Zusätzliche Hinweise zur Barrierefreiheit

### **Barrierefreiheit ist nicht nur für sehbehinderte Menschen wichtig**

Barrierefreies Webdesign richtet sich an alle Nutzer:innen, insbesondere an:

- **Menschen mit motorischen Einschränkungen**: Nutzer:innen, die Schwierigkeiten haben, eine Maus oder ein Touchpad zu verwenden, sind auf eine klare Tastaturnavigation angewiesen.
- **Menschen mit kognitiven Beeinträchtigungen**: Sie benötigen einfache Strukturen und klare Inhalte. Der Textaufbau sollte logisch und leicht nachvollziehbar sein.
- **Menschen mit Wahrnehmungsstörungen (z.B. Farbenblindheit, Auditive Verarbeitungsstörungen)**: Diese Nutzer:innen profitieren von klaren Kontrasten, gutem Farbumsatz und Untertiteln oder Transkripten für Videos.

---

### **Leichte und einfache Sprache**

Leichte und einfache Sprache verbessern die Verständlichkeit von Texten. Menschen mit Lernschwierigkeiten, geringen Sprachkenntnissen oder kognitiven Einschränkungen können so besser auf Inhalte zugreifen.

| **Sprache**           | **Beschreibung**                                                                                                                                               | **Beispiel für komplexen Text**                                                                                  | **Alternative in einfacher Sprache**                                                    | **Alternative in leichter Sprache**                                                    |
|-----------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------|
| **Komplexer Text**     | Normaler Text, der komplexe Sätze und Fachbegriffe enthält.                                                                                                     | "Die Implementierung neuer Features erfordert eine enge Zusammenarbeit zwischen den verschiedenen Fachabteilungen." | "Um neue Funktionen hinzuzufügen, müssen die Fachabteilungen eng zusammenarbeiten."      | "Um neue Funktionen hinzuzufügen, müssen die Abteilungen gut zusammenarbeiten."         |
| **Einfache Sprache**   | Klarer, einfacher Aufbau. Fachbegriffe werden vermieden oder erklärt.                                                                                            | "Es ist essenziell, dass wir in unserem Projektteam eine interdisziplinäre Herangehensweise an den Tag legen."      | "Es ist wichtig, dass das Team aus Fachleuten verschiedener Bereiche gut zusammenarbeitet."| "Es ist wichtig, dass das Team aus Fachleuten gut zusammenarbeitet."                    |
| **Leichte Sprache**    | Sehr kurze Sätze, kein Fachvokabular. Wird häufig auch durch Piktogramme oder Bilder unterstützt. Nur wesentliche Informationen.                                 | "Die strategische Ausrichtung des Unternehmens wird durch eine Vielzahl von Faktoren beeinflusst."                 | "Es gibt viele Gründe, die die Strategie des Unternehmens beeinflussen."                 | "Es gibt viele Gründe, die die Planung der Firma beeinflussen."                         |

---

### **Rechtliche Hinweise zu BFSG und Barrierefreiheitserklärung**

**Wichtiger Rechtlicher Hinweis:** Die hier erwähnten Tools (BFSG Check und Generator für Barrierefreiheitserklärung von KLXM) dienen ausschließlich als Hilfestellung und erste Orientierung. Sie ersetzen **nicht** die erforderliche rechtliche und technische Fachprüfung durch qualifizierte Expert:innen.

**Empfohlenes Vorgehen:**
1. Nutzen Sie die Tools als ersten Schritt zur Selbsteinschätzung
2. Lassen Sie Ihre Website durch zertifizierte Barrierefreiheits-Expert:innen prüfen
3. Konsultieren Sie rechtliche Beratung für die finale Barrierefreiheitserklärung
4. Dokumentieren Sie alle durchgeführten Maßnahmen und Tests

## Möglicher Ablauf

```mermaid
graph TD
    A[Beginne Inhaltserstellung] --> B{Verwendung vorgefertigter Inhaltsblöcke?}
    B -->|Ja| C[Folge integrierten Barrierefreiheitsrichtlinien]
    B -->|Nein| D[Erstelle benutzerdefinierten Inhalt]
    C --> E{Bilder hinzufügen?}
    D --> E
    E -->|Ja| F[Füge Alt-Text im Medienpool hinzu]
    E -->|Nein| G{Links hinzufügen?}
    F --> G
    G -->|Ja| H[Verwende beschreibenden Linktext]
    G -->|Nein| I{Video hinzufügen?}
    H --> I
    I -->|Ja| J[Füge Untertitel oder Transkripte hinzu]
    I -->|Nein| K[Prüfe Farbkontrast]
    J --> K
    K --> L[Teste Tastaturnavigation]
    L --> M[Verwende for_sa11y-Prüfer]
    M --> N[Führe BFSG-Check durch]
    N --> O{Probleme gefunden?}
    O -->|Ja| P[Behebe Probleme]
    O -->|Nein| Q{Erstellung eines PDFs notwendig?}
    P --> M
    Q -->|Ja| R[Erstelle barrierefreies PDF]
    Q -->|Nein| S[Erstelle Barrierefreiheitserklärung]
    R --> T{PDF barrierefrei?}
    T -->|Ja| S
    T -->|Nein| U{Alternativen möglich?}
    U -->|Ja| V[Biete alternative Formate an]
    U -->|Nein| W[Dokumentiere Einschränkungen]
    V --> S
    W --> X[Plane zukünftige Verbesserungen]
    X --> S
    S --> Y[Rechtliche/technische Prüfung beauftragen]
    Y --> Z[Veröffentliche Inhalt]
    Z --> AA[Ende]
```

---

## Links für weitere Informationen

- **Schritte zu einem inklusiven Kommunikationsdesign** (Deutscher Blinden- und Sehbehindertenverband e. V.): [leserlich.info](https://leserlich.info)
- **Barrierefreie PDF** (Knappschaft Bahn See): [bundesfachstelle-barrierefreiheit.de](https://www.bundesfachstelle-barrierefreiheit.de/DE/Fachwissen/Informationstechnik/Barrierefreie-PDF/barrierefreie-pdf_node)
