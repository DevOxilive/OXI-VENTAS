<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $productsBySubcategory = [
            'Bebidas' => [
                ['CocaCola600ML', 'Refresco Coca Cola 600ML', 12, 18],
                ['Pepsi600ML', 'Refresco Pepsi 600ML', 11, 17],
                ['Sprite600ML', 'Refresco Sprite 600ML', 11, 18],
                ['AguaCiel1L', 'Agua natural Ciel 1L', 9, 15],
                ['JumexMango1L', 'Jugo Jumex Mango 1L', 18, 25],
                ['BoingGuayaba500ML', 'Boing Guayaba 500ML', 12, 18],
                ['GatoradeAzul600ML', 'Bebida deportiva Gatorade', 17, 25],
            ],

            'Botanas' => [
                ['DoritosNacho', 'Doritos Nacho', 14, 22],
                ['TakisFuego', 'Takis Fuego', 13, 21],
                ['RufflesQueso', 'Ruffles Queso', 15, 24],
                ['SabritasOriginal', 'Sabritas Original', 13, 22],
                ['CheetosTorciditos', 'Cheetos Torciditos', 12, 20],
                ['TostitosSalsaVerde', 'Tostitos Salsa Verde', 14, 23],
                ['Churrumais', 'Churrumais', 8, 15],
            ],

            'Lácteos' => [
                ['MilkOneLiter', 'Leche Entera 1L', 20, 28],
                ['YogurtNatural1L', 'Yogurt Natural 1L', 24, 35],
                ['QuesoPanela250G', 'Queso Panela 250g', 38, 55],
                ['CremaLala450ML', 'Crema Lala 450ml', 26, 38],
                ['MantequillaBarra', 'Mantequilla en barra', 19, 29],
                ['LecheDeslactosada1L', 'Leche Deslactosada 1L', 21, 30],
                ['YogurtFresa1L', 'Yogurt Fresa 1L', 24, 35],
            ],

            'Enlatados' => [
                ['AtunDolores', 'Atún Dolores en agua', 14, 22],
                ['SardinaTomate', 'Sardina en tomate', 18, 28],
                ['ChilesLaCostena', 'Chiles La Costeña', 12, 20],
                ['EloteDorado', 'Elote dorado lata', 11, 18],
                ['FrijolesBayosLata', 'Frijoles bayos lata', 13, 21],
                ['ChampinonesLata', 'Champiñones lata', 16, 25],
                ['VerdurasMixtasLata', 'Verduras mixtas lata', 12, 19],
            ],

            'Cereales' => [
                ['Zucaritas', 'Cereal Zucaritas', 48, 65],
                ['ChocoKrispis', 'Cereal Choco Krispis', 47, 64],
                ['CornFlakes', 'Cereal Corn Flakes', 42, 58],
                ['AvenaQuaker', 'Avena Quaker', 20, 32],
                ['GranolaNatural', 'Granola natural', 30, 45],
                ['CerealIntegral', 'Cereal integral', 44, 60],
                ['AvenaInstantanea', 'Avena instantánea', 18, 29],
            ],

            'Dulces' => [
                ['CarlosV', 'Chocolate Carlos V', 7, 12],
                ['Mazapan', 'Mazapán de cacahuate', 5, 10],
                ['PaletaPayaso', 'Paleta Payaso', 12, 20],
                ['GomitasPanditas', 'Gomitas Panditas', 11, 18],
                ['Bubulubu', 'Chocolate Bubulubu', 8, 14],
                ['Duvalin', 'Dulce Duvalín', 5, 9],
                ['LucasMuecas', 'Dulce Lucas Muecas', 10, 17],
            ],

            'Limpieza' => [
                ['FabulosoOneLiter', 'Fabuloso 1L', 24, 35],
                ['CloroCloralex1L', 'Cloro Cloralex 1L', 12, 20],
                ['Pinol1L', 'Pinol 1L', 18, 29],
                ['AjaxPolvo', 'Ajax en polvo', 13, 22],
                ['JabonZote', 'Jabón Zote', 14, 24],
                ['DetergenteRoma1KG', 'Detergente Roma 1kg', 28, 42],
                ['Suavitel850ML', 'Suavitel 850ml', 24, 36],
            ],

            'Higiene personal' => [
                ['ShampooSedal', 'Shampoo Sedal', 35, 55],
                ['JabonDove', 'Jabón Dove', 14, 24],
                ['PastaColgate', 'Pasta dental Colgate', 19, 32],
                ['DesodoranteAxe', 'Desodorante Axe', 42, 65],
                ['ToallasHumedas', 'Toallas húmedas', 20, 34],
                ['PapelHigienico4Rollos', 'Papel higiénico 4 rollos', 28, 45],
                ['CepilloDental', 'Cepillo dental', 12, 25],
            ],

            'Panadería' => [
                ['PanBimboBlanco', 'Pan Bimbo blanco', 32, 45],
                ['PanIntegral', 'Pan integral', 35, 49],
                ['Mantecadas', 'Mantecadas paquete', 18, 28],
                ['DonasBimbo', 'Donas Bimbo', 17, 27],
                ['RolesCanela', 'Roles de canela', 19, 30],
                ['PanTostado', 'Pan tostado', 22, 34],
                ['ConchasPaquete', 'Conchas paquete', 20, 32],
            ],

            'Desechables' => [
                ['VasosPlastico50PZ', 'Vasos plástico 50 piezas', 18, 30],
                ['PlatosDesechables', 'Platos desechables', 20, 33],
                ['CucharasDesechables', 'Cucharas desechables', 12, 22],
                ['Servilletas', 'Servilletas paquete', 14, 25],
                ['BolsasBasura', 'Bolsas para basura', 25, 40],
                ['PapelAluminio', 'Papel aluminio', 22, 38],
                ['Popotes', 'Popotes paquete', 10, 18],
            ],

            'Medicamentos' => [
                ['Paracetamol500MG', 'Paracetamol 500mg', 18, 35],
                ['Ibuprofeno400MG', 'Ibuprofeno 400mg', 22, 42],
                ['AspirinaTabletas', 'Aspirina tabletas', 20, 38],
                ['Loratadina10MG', 'Loratadina 10mg', 25, 48],
                ['Omeprazol20MG', 'Omeprazol 20mg', 28, 55],
                ['Antigripal', 'Antigripal tabletas', 24, 45],
                ['SalDeUvas', 'Sal de Uvas', 8, 15],
            ],

            'Vitaminas' => [
                ['VitaminaC', 'Vitamina C', 35, 60],
                ['ComplejoB', 'Complejo B', 38, 65],
                ['Multivitaminico', 'Multivitamínico', 45, 80],
                ['VitaminaD', 'Vitamina D', 40, 75],
                ['Omega3', 'Omega 3', 55, 95],
                ['CalcioTabletas', 'Calcio tabletas', 38, 70],
                ['SueroOral', 'Suero oral', 12, 22],
            ],

            'Curación' => [
                ['Vendas', 'Vendas elásticas', 16, 30],
                ['Gasas', 'Gasas estériles', 12, 24],
                ['Alcohol', 'Alcohol 250ml', 14, 26],
                ['AguaOxigenada', 'Agua oxigenada', 10, 20],
                ['Curitas', 'Curitas caja', 18, 35],
                ['Algodon', 'Algodón paquete', 15, 28],
                ['CintaMicropore', 'Cinta Micropore', 18, 34],
            ],

            'Jarabes' => [
                ['JarabeTosAdulto', 'Jarabe para tos adulto', 45, 80],
                ['JarabeTosInfantil', 'Jarabe para tos infantil', 42, 78],
                ['MielConPropoleo', 'Miel con propóleo', 35, 65],
                ['AmbroxolJarabe', 'Ambroxol jarabe', 38, 72],
                ['LoratadinaJarabe', 'Loratadina jarabe', 40, 75],
                ['Broncolin', 'Broncolin', 36, 68],
                ['JarabeNatural', 'Jarabe natural', 30, 58],
            ],

            'Croquetas' => [
                ['DogChow1KG', 'Dog Chow 1kg', 55, 85],
                ['Pedigree1KG', 'Pedigree 1kg', 52, 82],
                ['Whiskas1KG', 'Whiskas 1kg', 58, 90],
                ['Minino1KG', 'Minino 1kg', 50, 78],
                ['Ganador1KG', 'Ganador 1kg', 48, 75],
                ['NupecBolsa', 'Nupec bolsa chica', 80, 125],
                ['CatChow1KG', 'Cat Chow 1kg', 60, 95],
            ],

            'Arena' => [
                ['ArenaGato5KG', 'Arena para gato 5kg', 55, 90],
                ['ArenaAglomerante', 'Arena aglomerante', 65, 105],
                ['ArenaAromatica', 'Arena aromática', 62, 99],
                ['ArenaNatural', 'Arena natural', 48, 80],
                ['ArenaPremium', 'Arena premium', 75, 120],
                ['ArenaEconomica', 'Arena económica', 38, 65],
                ['ArenaSilica', 'Arena sílica', 85, 135],
            ],

            'Premios' => [
                ['PremiosPerro', 'Premios para perro', 28, 50],
                ['PremiosGato', 'Premios para gato', 25, 48],
                ['Huesitos', 'Huesitos carnaza', 20, 38],
                ['GalletasMascota', 'Galletas mascota', 22, 40],
                ['SnackDental', 'Snack dental', 30, 55],
                ['AtunGato', 'Atún para gato', 18, 32],
                ['SobresMascota', 'Sobres alimento mascota', 15, 28],
            ],

            'Cuadernos' => [
                ['CuadernoProfesional', 'Cuaderno profesional', 25, 45],
                ['CuadernoFormaItaliana', 'Cuaderno forma italiana', 18, 35],
                ['LibretaNotas', 'Libreta de notas', 12, 25],
                ['BlockDibujo', 'Block de dibujo', 20, 38],
                ['CuadernoRayado', 'Cuaderno rayado', 22, 40],
                ['CuadernoCuadro', 'Cuaderno cuadro chico', 22, 40],
                ['AgendaEscolar', 'Agenda escolar', 35, 65],
            ],

            'Plumas' => [
                ['PlumaBicAzul', 'Pluma Bic azul', 4, 8],
                ['PlumaBicNegra', 'Pluma Bic negra', 4, 8],
                ['LapizMirado', 'Lápiz Mirado', 3, 7],
                ['MarcadorSharpie', 'Marcador Sharpie', 12, 25],
                ['Colores12PZ', 'Colores 12 piezas', 28, 50],
                ['Corrector', 'Corrector líquido', 10, 20],
                ['Resaltador', 'Resaltador', 8, 16],
            ],

            'Oficina' => [
                ['HojasCarta100PZ', 'Hojas carta 100 piezas', 45, 70],
                ['FolderManila', 'Folder manila', 5, 10],
                ['ClipsCaja', 'Clips caja', 10, 20],
                ['GrapasCaja', 'Grapas caja', 12, 22],
                ['CintaAdhesiva', 'Cinta adhesiva', 10, 20],
                ['PegamentoBarra', 'Pegamento barra', 8, 18],
                ['TijerasEscolares', 'Tijeras escolares', 15, 30],
            ],

            'Cocina' => [
                ['EsponjaTrastes', 'Esponja para trastes', 8, 15],
                ['FibraVerde', 'Fibra verde', 7, 14],
                ['TrapoCocina', 'Trapo de cocina', 12, 24],
                ['EncendedorCocina', 'Encendedor cocina', 15, 30],
                ['Cerillos', 'Cerillos', 5, 10],
                ['Servitoallas', 'Servitoallas', 18, 32],
                ['GuantesCocina', 'Guantes de cocina', 22, 40],
            ],

            'Decoración' => [
                ['VelaAromatica', 'Vela aromática', 20, 38],
                ['FlorArtificial', 'Flor artificial', 18, 35],
                ['Portarretrato', 'Portarretrato', 25, 50],
                ['MantelPlastico', 'Mantel plástico', 15, 30],
                ['AdornoPequeno', 'Adorno pequeño', 12, 25],
                ['GloboDecorativo', 'Globo decorativo', 8, 15],
                ['CintaDecorativa', 'Cinta decorativa', 10, 22],
            ],

            'Organización' => [
                ['CajaPlastica', 'Caja plástica', 35, 65],
                ['GanchoRopa', 'Gancho para ropa', 12, 25],
                ['CanastaPlastica', 'Canasta plástica', 28, 55],
                ['BolsaOrganizadora', 'Bolsa organizadora', 25, 50],
                ['ContenedorHermetico', 'Contenedor hermético', 30, 60],
                ['PinzasRopa', 'Pinzas para ropa', 10, 20],
                ['CestoBasura', 'Cesto de basura', 40, 75],
            ],
        ];

        $id = 1;

        foreach ($productsBySubcategory as $subcategoryName => $products) {
            $subcategory = DB::table('subcategories')
                ->where('name', $subcategoryName)
                ->first();

            if (!$subcategory) {
                continue;
            }

            foreach ($products as [$name, $description, $cost, $salePrice]) {
                DB::table('products')->updateOrInsert(
                    ['id' => $id],
                    [
                        'name' => $name,
                        'description' => $description,
                        'cost' => $cost,
                        'sale_price' => $salePrice,
                        'category_id' => $subcategory->category_id,
                        'subcategory_id' => $subcategory->id,
                        'active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                $id++;
            }
        }
    }
}