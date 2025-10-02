================================================
PHPSERVERINFO - MODERN SZERVER MONITORING DASHBOARD
================================================

Ez a projekt egy modern, webalapú, nyílt forráskódú szerver monitorozó megoldás,
amely PHP nyelven íródott, és a Bootstrap 5 keretrendszert használja.
Célja, hogy egy letisztult, reszponzív felületen mutassa be a futtató webszerver
részletes hardver- és szoftveradatait.

------------------------------------------------
FŐBB JELLEMZŐK ÉS FUNKCIÓK
------------------------------------------------

1.  MODERN FELÜLET
    * **Reszponzív Design:** Mobilbarát, Bootstrap 5-re épülő elrendezés.
    * **Téma Váltó:** Könnyen váltható világos és sötét mód (a böngésző tárolja a preferenciát).

2.  OPERÁCIÓS RENDSZER TÁMOGATÁS ÉS DETEKTÁLÁS
    * **Multiplatform:** Támogatja a **Linux** és a **Windows Server** környezeteket (a Windows adatgyűjtéshez szükség lehet a PHP 'shell_exec' engedélyezésére).
    * **Dinamikus Ikonok:** Automatikusan megjeleníti a futó OS (pl. Windows, Ubuntu, Debian) ikonját.

3.  RÉSZLETES RENDSZERINFORMÁCIÓK
    * **OS/Uptime:** Részletes operációs rendszer verzió és a szerver futási ideje (uptime).
    * **CPU:** A CPU modelljének és a magok számának kijelzése.
    * **Hálózat:** Az elsődleges hálózati interfész (IP és MAC cím) megjelenítése.

4.  METRIKUS MONITORING (PROGRESS BAROKKAL)
    * **CPU Terhelés:** Látványos progress bar a processzor terhelésének azonnali vizuális megjelenítésére (Linux alatt az 1-perces load average alapján). A sáv színe figyelmeztet (70% felett sárga, 90% felett piros).
    * **Memória:** A teljes, használt és szabad memória mennyisége, százalékos kihasználtsági sávval.
    * **Háttértár:** A fő partíció (Linux: `/`, Windows: `C:\`) teljes, szabad és használt lemezterületének listázása, szintén dinamikus progress barral.

5.  FELHASZNÁLT TECHNOLÓGIÁK
    * PHP (backend adatgyűjtés)
    * HTML5 / CSS3 (struktúra és stílus)
    * JavaScript (témaváltás)
    * Bootstrap 5 (UI/UX keretrendszer)
    * Font Awesome (ikonok)

------------------------------------------------
TELEPÍTÉS ÉS HASZNÁLAT
------------------------------------------------

1.  **Fájlok másolása:** Helyezze az `index.php` és `style.css` fájlokat a webszerver dokumentumgyökerébe (pl. `/var/www/html/`).
2.  **Elérés:** Nyissa meg a fájlt böngészőben (pl. `http://localhost/index.php`).
3.  **Téma Váltás:** A jobb felső sarokban található gombbal válthat a világos és sötét mód között.
4.  **Adatok frissítése:** Az adatok statikusak, az oldal frissítése szükséges a legfrissebb állapot lekérdezéséhez.

------------------------------------------------
FONTOS JOGOSULTSÁGI FIGYELMEZTETÉS
------------------------------------------------

A részletes rendszer- és hálózati adatok (különösen Windows Server és bizonyos Linux adatok) lekérdezéséhez a PHP **`shell_exec()`** funkciót használja.

Amennyiben a dashboard 'N/A' vagy hiányos adatokat mutat, valószínűleg a webszerver futtató felhasználójának (pl. `www-data` vagy `IIS AppPool`) **nincs megfelelő jogosultsága** a következőkhöz:

* **Linux:** A `/proc/cpuinfo`, `/proc/meminfo`, `/proc/uptime` és bizonyos hálózati parancsok futtatásához.
* **Windows:** A **`wmic`** parancs futtatásához.

Biztonsági okokból a legtöbb hosting környezetben a `shell_exec` alapértelmezetten tiltva van.
