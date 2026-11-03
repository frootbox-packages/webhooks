---
title: Webhooks
---
**Webhooks** informieren andere Dienste, wenn sich auf Deiner Website
etwas ändert, z. B. einen Shop, ein Newsletter-Werkzeug oder eine
Automatisierung wie Zapier oder n8n. Nach jeder gewählten Aktion geht im
Hintergrund eine Nachricht an die Adresse.

## Webhook einrichten

Wähle **Neuer Webhook**, gib ihm einen Titel und die Adresse des
Empfängers. Sie muss mit `https://` beginnen und aus dem Internet
erreichbar sein. Hake die Aktionen an, bei denen er senden soll.
**Alle** in einer Gruppe umfasst auch Aktionen, die später dazukommen,
z. B. mit einem neuen Modul.

Nach dem Speichern siehst Du einmal das **Geheimnis**. Gib es dem
Empfänger: Er prüft damit, dass jede Nachricht von Deiner Website kommt.
Ist das Geheimnis verloren, erzeuge ein **Neues Geheimnis**; das alte
gilt dann sofort nicht mehr.

## Was eine Nachricht enthält

Den Namen der Aktion, wer sie ausgeführt hat, die Eingabe und vom
Ergebnis die ID. Passwörter und andere Geheimnisse bleiben draußen.
Bedenke, dass die Eingabe personenbezogene Daten enthalten kann, z. B.
die Einsendungen eines Formulars: Wähle nur, was der Empfänger braucht.

## Zustellungen

Unter dem Webhook stehen die Nachrichten der letzten 30 Tage mit der
Antwort der Adresse. Eine gescheiterte Nachricht wird erneut versucht,
8-mal über etwa 18 Stunden. **Erneut senden** beginnt von vorn.
**Probe senden** schickt sofort eine Probenachricht.

Kommt 5 Tage lang keine Nachricht an, wird der Webhook pausiert, und die
Seitenleiste zeigt eine Zahl. Prüfe die Adresse und wähle
**Fortsetzen**.
