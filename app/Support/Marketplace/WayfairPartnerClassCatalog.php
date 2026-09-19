<?php

namespace App\Support\Marketplace;

/**
 * Partner Home product-addition class picker (Categories / Classes / Class Definition).
 * Wayfair's public taxonomyCategories query is often Access Denied, so this catalog
 * mirrors the portal tree and known CLIDs from class-definition text.
 */
class WayfairPartnerClassCatalog
{
    /**
     * @return list<string>
     */
    public static function groupNames(): array
    {
        return [
            'Accent Furniture',
            'Accommodations',
            'Appliances',
            'AV/TV',
            'Baby Gear',
            'Bathroom',
            'Bedding',
            'Cabinetry',
            'Commercial Renovation',
            'Decorative Accent - Home Accents',
            'Decorative Accent - Seasonal',
            'Decorative Accent - Tabletop',
            'Decorative Accent - Wall Decor',
            'Electrical',
            'Entryway',
            'Exercise & Fitness',
            'Flooring',
            'Furniture',
            'Garage & Workshop',
            'Garden',
            'Hardware',
            'Holiday',
            'Home Improvement',
            'Kids',
            'Kitchen',
            'Lighting',
            'Luggage',
            'Mattress',
            'Medical',
            'Music',
            'Nursery',
            'Office',
            'Outdoor Decor',
            'Outdoor Furniture',
            'Party',
            'Patio',
            'Pet',
            'Plumbing',
            'Rugs',
            'Safety',
            'School & Office Supplies',
            'Sports',
            'Storage',
            'Teen',
            'Textiles',
            'Tools',
            'Toys',
            'Window Treatments',
            'Commercial Furniture',
            'Closet',
            'Laundry',
            'Jewelry',
            'HVAC',
        ];
    }

    /**
     * @return list<array{id: string, name: string, category: string, definition: string}>
     */
    public static function classes(): array
    {
        $rows = [];
        foreach (self::rawClasses() as $row) {
            $id = trim((string) ($row[0] ?? ''));
            $name = trim((string) ($row[1] ?? ''));
            $category = trim((string) ($row[2] ?? ''));
            $definition = trim((string) ($row[3] ?? ''));
            if ($name === '' || $category === '') {
                continue;
            }
            $rows[] = [
                'id' => $id,
                'name' => $name,
                'category' => $category,
                'definition' => $definition !== '' ? $definition : self::defaultDefinition($name),
            ];
        }

        return $rows;
    }

    /**
     * @return array{groups: list<array{name: string, count: int}>, classes: list<array{id: string, name: string, category: string, definition: string, path: string}>}
     */
    public static function search(string $query = '', string $group = ''): array
    {
        $query = trim($query);
        $group = trim($group);
        $q = mb_strtolower($query);
        $all = self::classes();
        $matched = [];
        foreach ($all as $row) {
            if ($group !== '' && strcasecmp($row['category'], $group) !== 0) {
                continue;
            }
            if ($q !== '' && ! self::rowMatches($row, $q)) {
                continue;
            }
            $matched[] = self::present($row);
        }

        $groups = [];
        foreach (self::groupNames() as $name) {
            $count = 0;
            foreach ($all as $row) {
                if (strcasecmp($row['category'], $name) !== 0) {
                    continue;
                }
                if ($q !== '' && ! self::rowMatches($row, $q) && ! str_contains(mb_strtolower($name), $q)) {
                    continue;
                }
                $count++;
            }
            $groups[] = ['name' => $name, 'count' => $count];
        }

        return ['groups' => $groups, 'classes' => $matched];
    }

    /**
     * @return array{id: string, name: string, category: string, definition: string, path: string}|null
     */
    public static function findById(string $id): ?array
    {
        $id = trim($id);
        if ($id === '' || ! preg_match('/^\d+$/', $id)) {
            return null;
        }
        foreach (self::classes() as $row) {
            if ((string) $row['id'] === $id) {
                return self::present($row);
            }
        }

        return null;
    }

    /**
     * @return array{id: string, name: string, category: string, definition: string, path: string}|null
     */
    public static function findByName(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        $lower = mb_strtolower($name);
        foreach (self::classes() as $row) {
            if (mb_strtolower($row['name']) === $lower) {
                return self::present($row);
            }
        }

        return null;
    }

    /**
     * @param  array{id: string, name: string, category: string, definition: string}  $row
     * @return array{id: string, name: string, category: string, definition: string, path: string}
     */
    public static function present(array $row): array
    {
        $id = trim((string) ($row['id'] ?? ''));
        $name = trim((string) ($row['name'] ?? ''));

        return [
            'id' => $id,
            'name' => $name,
            'category' => trim((string) ($row['category'] ?? '')),
            'definition' => trim((string) ($row['definition'] ?? '')),
            'path' => $id !== '' ? $name.' ('.$id.')' : $name,
        ];
    }

    /**
     * @param  array{id: string, name: string, category: string, definition: string}  $row
     */
    private static function rowMatches(array $row, string $q): bool
    {
        $hay = mb_strtolower(trim($row['name'].' '.$row['category'].' '.$row['id']));
        if (str_contains($hay, $q)) {
            return true;
        }
        $words = preg_split('/\s+/', $q) ?: [];
        foreach ($words as $word) {
            if (mb_strlen($word) >= 2 && str_contains($hay, $word)) {
                return true;
            }
        }

        return false;
    }

    private static function defaultDefinition(string $name): string
    {
        return 'CLASS OVERVIEW: Select this class for '.$name.'. Use this class when the product is sold and merchandised as '.$name.' on Wayfair. Review the class definition in Partner Home if you are unsure.';
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private static function rawClasses(): array
    {
        $carts = <<<'TXT'
CLASS OVERVIEW: Select this class for carts and stands that can be used in commercial settings, such as utility carts, AV carts, book carts, laptop carts and mail carts. These products are intended for a commercial setting to store, organize and transport office equipment, like computers, laptops and printers, but may also be used in residential settings. These products are typically mobile/include casters and provide a way to maximize space in a sleek and simple way. Utility Carts are essentially rolling carts that can serve multiple functions in multiple settings, such as in an office, classroom, living room, kitchen or bathroom.

Some Utility carts are only intended to be used in commercial settings. Example of Utility Carts and other product types are outlined in the attached document.

DO NOT CLASSIFY: Do not classify tool carts here — those should be classified in Tool Cabinets (CLID: 14270). Do not classify bookcases here — those should be classified in Bookcases (CLID: 38). Do not classify residential cabinets like Bar Carts (CLID 61472), Bathroom Storage (CLID 57444), Hampers & Baskets (CLID 51550), Residential Serving Carts (226). Do not classify floor cabinets (CLID 12239) or Storage Cabinets (CLID 157) of this class, unless they only serve a convenience purpose. Do not classify TV mounts (CLID 442) or Console Tables (CLID 443) in this class. Do not classify floor standing TV mounts in this class.
TXT;

        return [
            ['', 'Carts & Stands', 'Accent Furniture', $carts],
            ['', 'Multimedia Storage', 'Accent Furniture', 'CLASS OVERVIEW: Select this class for multimedia storage cabinets and towers used to organize media, gaming, or AV components. These are typically stationary residential storage pieces, not rolling utility carts.'],
            ['443', 'Console Tables', 'Accent Furniture', 'CLASS OVERVIEW: Select this class for console tables and sofa tables used against a wall or behind a sofa. DO NOT CLASSIFY TV stands, carts, or desks here.'],
            ['61472', 'Bar Carts', 'Accent Furniture', 'CLASS OVERVIEW: Select this class for residential bar carts and serving trolleys used to store and serve drinks. DO NOT CLASSIFY commercial utility carts here — use Carts & Stands.'],
            ['', 'Accent Chests', 'Accent Furniture', ''],
            ['', 'Accent Chairs', 'Accent Furniture', ''],
            ['', 'Hotel Furniture', 'Accommodations', ''],
            ['', 'Hospitality Carts', 'Accommodations', ''],
            ['', 'Refrigerators', 'Appliances', ''],
            ['', 'Microwaves', 'Appliances', ''],
            ['', 'Speaker Stands', 'AV/TV', 'CLASS OVERVIEW: Select this class for speaker stands and speaker pedestals that hold bookshelf or satellite speakers. These are typically stationary stands designed for home audio. DO NOT CLASSIFY TV stands, lighting stands, or microphone stands here.'],
            ['', 'TV Stands & Entertainment Centers', 'AV/TV', 'CLASS OVERVIEW: Select this class for TV stands, entertainment centers, and media consoles that hold a television and related components. DO NOT CLASSIFY rolling AV carts (Carts & Stands) or wall-mounted TV mounts (CLID 442) here.'],
            ['', 'Audio Towers', 'AV/TV', 'CLASS OVERVIEW: Select this class for floor-standing audio towers and speaker towers. DO NOT CLASSIFY speaker stands, sound bars, or TV stands here.'],
            ['442', 'TV Mounts', 'AV/TV', 'CLASS OVERVIEW: Select this class for wall-mounted or floor-standing TV mounts and brackets. DO NOT CLASSIFY TV stands or rolling AV carts here.'],
            ['', 'Baby Activity Centers', 'Baby Gear', ''],
            ['', 'Changing Tables', 'Baby Gear', ''],
            ['57444', 'Bathroom Storage', 'Bathroom', 'CLASS OVERVIEW: Select this class for residential bathroom storage such as over-toilet cabinets and bath organizers. DO NOT CLASSIFY commercial utility carts here.'],
            ['51550', 'Hampers & Baskets', 'Bathroom', 'CLASS OVERVIEW: Select this class for laundry hampers and bathroom baskets. DO NOT CLASSIFY carts or cabinets here.'],
            ['', 'Towel Bars, Racks, and Stands', 'Bathroom', 'CLASS OVERVIEW: Select this class for towel bars, towel racks, and freestanding towel stands used in residential bathrooms.'],
            ['', 'Comforters & Sets', 'Bedding', ''],
            ['', 'Sheets', 'Bedding', ''],
            ['', 'Kitchen Cabinets', 'Cabinetry', ''],
            ['', 'Bathroom Vanities', 'Cabinetry', ''],
            ['', 'Commercial Casework', 'Commercial Renovation', ''],
            ['', 'Decorative Bowls', 'Decorative Accent - Home Accents', ''],
            ['1318', 'Wall Art', 'Decorative Accent - Wall Decor', 'CLASS OVERVIEW: Select this class for wall art, canvas prints, and framed artwork.'],
            ['', 'Seasonal Figurines', 'Decorative Accent - Seasonal', ''],
            ['', 'Vases', 'Decorative Accent - Tabletop', ''],
            ['', 'Light Switches & Outlets', 'Electrical', ''],
            ['', 'Console Tables & Hall Trees', 'Entryway', ''],
            ['', 'Exercise Bikes', 'Exercise & Fitness', ''],
            ['', 'Area Rug Pads', 'Flooring', ''],
            ['38', 'Bookcases', 'Furniture', 'CLASS OVERVIEW: Select this class for bookcases and etageres used to store books and display items. DO NOT CLASSIFY rolling book carts here — use Carts & Stands.'],
            ['', 'Bedsteads', 'Furniture', 'CLASS OVERVIEW: Select this class for bed frames and bedsteads. DO NOT CLASSIFY mattresses, nightstands, or headboards sold separately if they have their own class.'],
            ['', 'Dressers & Chests', 'Furniture', 'CLASS OVERVIEW: Select this class for dressers, chests of drawers, and bedroom storage chests.'],
            ['', 'Nightstands', 'Furniture', 'CLASS OVERVIEW: Select this class for adult nightstands and bedside tables. DO NOT CLASSIFY kids or teen nightstands, plant stands, or speaker stands here.'],
            ['', 'Office Chairs', 'Office', 'CLASS OVERVIEW: Select this class for office task chairs, executive chairs, and desk chairs. DO NOT CLASSIFY dining chairs, stools, or gaming chairs if they have a more specific class.'],
            ['', 'Desks', 'Office', ''],
            ['', 'Filing Cabinets', 'Office', ''],
            ['', 'Tool Storage', 'Garage & Workshop', ''],
            ['14270', 'Tool Cabinets', 'Tools', 'CLASS OVERVIEW: Select this class for tool cabinets and tool carts used to store tools. DO NOT CLASSIFY office utility carts here — use Carts & Stands.'],
            ['', 'Plant Stands & Tables', 'Garden', 'CLASS OVERVIEW: Select this class for plant stands, plant tables, and plant pedestals used to display potted plants indoors or outdoors.'],
            ['', 'Mailbox Posts & Stands', 'Garden', 'CLASS OVERVIEW: Select this class for mailbox posts, mailbox stands, and mailbox support hardware.'],
            ['', 'Door Hardware', 'Hardware', ''],
            ['', 'Christmas Tree Stands & Accessories', 'Holiday', 'CLASS OVERVIEW: Select this class for Christmas tree stands, tree collars, and tree stand accessories. DO NOT CLASSIFY plant stands or speaker stands here.'],
            ['', 'Holiday Lighting', 'Holiday', ''],
            ['', 'Ladders', 'Home Improvement', ''],
            ['', 'Kids Nightstands', 'Kids', 'CLASS OVERVIEW: Select this class for nightstands merchandised for children. DO NOT CLASSIFY adult nightstands or teen nightstands here.'],
            ['', 'Kids Beds', 'Kids', ''],
            ['', 'Kitchen Islands', 'Kitchen', ''],
            ['226', 'Residential Serving Carts', 'Kitchen', 'CLASS OVERVIEW: Select this class for residential kitchen serving carts and kitchen utility carts. DO NOT CLASSIFY commercial utility carts here — use Carts & Stands.'],
            ['', 'Floor Lamps', 'Lighting', 'CLASS OVERVIEW: Select this class for finished floor lamps sold as lighting fixtures. DO NOT CLASSIFY photography light stands, tripods, or lamp replacement parts here.'],
            ['', 'Table Lamps', 'Lighting', 'CLASS OVERVIEW: Select this class for finished table lamps. DO NOT CLASSIFY photography lighting or light stands here.'],
            ['', 'Lighting Accessories', 'Lighting', 'CLASS OVERVIEW: Select this class for lighting accessories such as shades, finials, and replacement parts. DO NOT CLASSIFY complete lamps or photography light stands here.'],
            ['', 'Photography Lighting', 'Lighting', 'CLASS OVERVIEW: Select this class for photography and video lighting kits, softboxes, and studio lights. Related stands may belong with lighting stands or tripods when the primary purpose is to hold lights.'],
            ['', 'Light Stands & Tripods', 'Lighting', 'CLASS OVERVIEW: Select this class for lighting stands, light stand tripods, and adjustable lighting support stands used with photography or video lights. DO NOT CLASSIFY speaker stands, music stands, or finished floor lamps here.'],
            ['', 'Ceiling Lighting', 'Lighting', ''],
            ['', 'Suitcases', 'Luggage', ''],
            ['', 'Mattresses', 'Mattress', ''],
            ['', 'Mobility Aids', 'Medical', ''],
            ['', 'Music Stands', 'Music', 'CLASS OVERVIEW: Select this class for sheet music stands and orchestra music stands. DO NOT CLASSIFY guitar stands, microphone stands, or lighting stands here.'],
            ['', 'Microphone Stands', 'Music', 'CLASS OVERVIEW: Select this class for microphone stands, boom stands, and mic stand accessories. DO NOT CLASSIFY music stands, speaker stands, or lighting stands here.'],
            ['', 'Guitar Stands', 'Music', 'CLASS OVERVIEW: Select this class for guitar stands, instrument stands, and multi-guitar racks. DO NOT CLASSIFY hangers, music stands, or keyboard stands here.'],
            ['', 'Keyboard Stands', 'Music', 'CLASS OVERVIEW: Select this class for keyboard and piano stands. DO NOT CLASSIFY guitar stands or music stands here.'],
            ['', 'Guitar Hangers', 'Music', 'CLASS OVERVIEW: Select this class for wall-mounted guitar hangers and instrument hangers.'],
            ['', 'Drum Thrones', 'Music', 'CLASS OVERVIEW: Select this class for drum thrones and musician stools sold for percussion. DO NOT CLASSIFY residential bar stools here.'],
            ['', 'Guitar Stools', 'Music', 'CLASS OVERVIEW: Select this class for guitar stools and musician seating. DO NOT CLASSIFY residential bar stools or drum thrones here.'],
            ['', 'Amplifiers', 'Music', 'CLASS OVERVIEW: Select this class for guitar and instrument amplifiers.'],
            ['', 'Cribs', 'Nursery', ''],
            ['', 'Patio Umbrella Stands & Bases', 'Patio', 'CLASS OVERVIEW: Select this class for patio umbrella bases and umbrella stands. DO NOT CLASSIFY umbrellas, plant stands, or lighting stands here.'],
            ['', 'Hammock Stands & Accessories', 'Outdoor Furniture', 'CLASS OVERVIEW: Select this class for hammock stands and hammock stand accessories. DO NOT CLASSIFY hammocks sold without a stand if they have their own class.'],
            ['', 'Patio Dining Sets', 'Outdoor Furniture', ''],
            ['', 'Garden Statues', 'Outdoor Decor', ''],
            ['', 'Party Decorations', 'Party', ''],
            ['', 'Banner Stands & Booth Displays', 'School & Office Supplies', 'CLASS OVERVIEW: Select this class for banner stands, trade-show booth displays, and retractable sign stands. DO NOT CLASSIFY lighting stands, music stands, or furniture carts here.'],
            ['', 'Pet Beds', 'Pet', ''],
            ['', 'Faucets', 'Plumbing', ''],
            ['', 'Area Rugs', 'Rugs', ''],
            ['', 'Safes', 'Safety', ''],
            ['', 'Sports Racks', 'Sports', ''],
            ['157', 'Storage Cabinets', 'Storage', 'CLASS OVERVIEW: Select this class for storage cabinets used to organize household items. DO NOT CLASSIFY rolling utility carts here — use Carts & Stands.'],
            ['12239', 'Floor Cabinets', 'Storage', 'CLASS OVERVIEW: Select this class for floor cabinets. DO NOT CLASSIFY rolling carts or tool cabinets here.'],
            ['', 'Closet Systems', 'Closet', ''],
            ['', 'Laundry Centers', 'Laundry', ''],
            ['', 'Teen Nightstands', 'Teen', 'CLASS OVERVIEW: Select this class for nightstands merchandised for teens. DO NOT CLASSIFY adult nightstands or kids nightstands here.'],
            ['', 'Throw Blankets', 'Textiles', ''],
            ['', 'Hand Tools', 'Tools', ''],
            ['', 'Outdoor Play', 'Toys', ''],
            ['', 'Curtains & Drapes', 'Window Treatments', ''],
            ['', 'Commercial Seating', 'Commercial Furniture', ''],
            ['', 'Fine Jewelry', 'Jewelry', ''],
            ['', 'Air Conditioners', 'HVAC', ''],
        ];
    }
}
