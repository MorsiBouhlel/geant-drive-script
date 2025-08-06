import csv
from concurrent.futures import ThreadPoolExecutor, as_completed
import argparse

IGNORED_COLUMNS = {}

def load_file(filepath, id_column='EAN', delimiter=';'):
    data = {}
    with open(filepath, mode='r', encoding='utf-8') as file:
        reader = csv.DictReader(file, delimiter=delimiter)
        for row in reader:
            key = row.get(id_column, '').strip()
            if not key:
                continue
            data[key] = row
    return data, reader.fieldnames

def compare_rows(ean, old_row, new_row):
    changes = []
    for key in new_row:
        if key in IGNORED_COLUMNS:
            continue
        old_value = old_row.get(key, '')
        new_value = new_row[key]
        if old_value != new_value:
            changes.append((key, old_value, new_value))
    return changes

def is_modified_or_added(ean, old_data, new_data):
    if ean not in old_data:
        return 'added', new_data[ean], []
    else:
        diffs = compare_rows(ean, old_data[ean], new_data[ean])
        if diffs:
            return 'modified', new_data[ean], diffs
    return None

def extract_diff_threaded_with_log(
    old_file,
    new_file,
    output_data_file,
    id_column='EAN',
    delimiter=';',
    max_workers=8
):
    old_data, _ = load_file(old_file, id_column, delimiter)
    new_data, fieldnames = load_file(new_file, id_column, delimiter)

    results = []
    logs = []
    deleted_rows = []

    # Comparaison multithread : ajout / modif
    with ThreadPoolExecutor(max_workers=max_workers) as executor:
        futures = {
            executor.submit(is_modified_or_added, key, old_data, new_data): key
            for key in new_data
        }

        for future in as_completed(futures):
            result = future.result()
            if result:
                action, row, diffs = result
                results.append(row)

                if action == 'added':
                    logs.append({
                        'EAN': row[id_column],
                        'action': 'added',
                        'field': '',
                        'old_value': '',
                        'new_value': ''
                    })
                elif action == 'modified':
                    for field, old_val, new_val in diffs:
                        logs.append({
                            'EAN': row[id_column],
                            'action': 'modified',
                            'field': field,
                            'old_value': old_val,
                            'new_value': new_val
                        })



    # 📁 Écriture des mises à jour
    with open(output_data_file, mode='w', encoding='utf-8', newline='') as out:
        writer = csv.DictWriter(out, fieldnames=fieldnames, delimiter=delimiter)
        writer.writeheader()
        cleaned = [{k: v for k, v in row.items() if k in fieldnames} for row in results]
        writer.writerows(cleaned)

def main():
    # Configuration des arguments en ligne de commande
    parser = argparse.ArgumentParser(description="Compare deux fichiers CSV et génère des fichiers de différences.")
    parser.add_argument('old_file', type=str, help="Chemin vers l'ancien fichier CSV")
    parser.add_argument('new_file', type=str, help="Chemin vers le nouveau fichier CSV")
    parser.add_argument('--output-data', type=str, default='updated.csv', help="Chemin vers le fichier de sortie pour les données mises à jour")
    parser.add_argument('--id-column', type=str, default=' EAN', help="Nom de la colonne ID (par défaut: EAN)")
    parser.add_argument('--delimiter', type=str, default=';', help="Délimiteur CSV (par défaut: ;)")
    parser.add_argument('--max-workers', type=int, default=8, help="Nombre maximum de workers pour le multithreading (par défaut: 8)")

    args = parser.parse_args()

    # Appel de la fonction principale avec les arguments
    extract_diff_threaded_with_log(
        old_file=args.old_file,
        new_file=args.new_file,
        output_data_file=args.output_data,
        id_column=args.id_column,
        delimiter=args.delimiter,
        max_workers=args.max_workers
    )

if __name__ == '__main__':
    main()
