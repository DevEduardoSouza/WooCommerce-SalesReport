<?php
/*
Plugin Name: WooCommerce Sales Report
Description: Gera um relatório de vendas para produtos no WooCommerce.
Version: 1.1.0
Author: Eduardo Souza
*/

$btn_adicionado = false;

// Função para adicionar um botão na página de administração de produtos
function add_btn_adm_order() {
    global $post_type, $btn_adicionado;

    if ($post_type === 'shop_order' && !$btn_adicionado) {
        $btn_adicionado = true;
?>

<div class="alignleft actions">
    <button type="button" class="button action" id="btn-baixar-planhia">Exportar em planilha</button>
</div>

<script>
    jQuery(document).ready(function ($) {
        $('#btn-baixar-planhia').on('click', function () {
            var selectedDate = $('#filter-by-date').val();
            var startDate = $('#scanner_filter_order_date_from').val();
            var endDate = $('#scanner_filter_order_date_to').val();

            var data = {
                action: 'get_order_content',
                security: '<?php echo wp_create_nonce("get-order-content-nonce"); ?>',
                selected_date: selectedDate,
                start_date: startDate,
                end_date: endDate
            };

            $.ajax({
                type: 'GET',
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                data: data,
                success: function (response) {
                    var ordersData = response.data;
                    var productsMap = {};

                    // Loop através dos pedidos
                    ordersData.forEach(function (order) {
                        // Loop através dos itens do pedido
                        order.order_items.forEach(function (item) {
                            var productCode = item.product_code;

                            // Se o produto ainda não foi registrado, inicialize os dados
                            if (!productsMap[productCode]) {
                                productsMap[productCode] = {
                                    product_name: item.product_name,
                                    product_code: productCode,
                                    quantity_sold: 0,
                                    unit_price: parseFloat(item.price.replace(/[^\d.-]/g, '')), // Converte o preço para número
                                    total_sales: 0
                                };
                            }

                            // Atualize os dados do produto com as informações do item
                            productsMap[productCode].quantity_sold += parseInt(item.quantity);
                            productsMap[productCode].total_sales += parseFloat(item.price.replace(/[^\d.-]/g, '')) * parseInt(item.quantity);
                        });
                    });

                    // Arredonda o valor total para evitar problemas com casas decimais
                    Object.values(productsMap).forEach(function (product) {
                        product.total_sales = 'R$  ' + (Math.round(product.total_sales * 100) / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                        product.unit_price = 'R$  ' + parseFloat(product.unit_price).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                    });

                    // Converter os dados para o formato CSV
                    var csvContent = "sku (código),Nome do Produto,Quantidade Vendida,Preço Unitário,Total\n";

                    Object.values(productsMap).forEach(function (product) {
                        csvContent += `"${product.product_code}","${product.product_name}","${product.quantity_sold}","${product.unit_price}","${product.total_sales}"\n`;
                    });

                    // Criar um link de download
                    var encodedUri = encodeURI(csvContent);
                    var link = document.createElement("a");
                    link.setAttribute("href", "data:text/csv;charset=utf-8," + encodeURIComponent(csvContent));
                    link.setAttribute("download", "vendas.csv");
                    document.body.appendChild(link);

                    link.style.textAlign = "center";

                    // Simular o clique no link para iniciar o download
                    link.click();
                },
                error: function (error) {
                    console.error(error);
                }
            });
        });
    });
</script>

<?php
    }
}
add_action('manage_posts_extra_tablenav', 'add_btn_adm_order');

function get_order_content() {
    $date = isset($_GET['selected_date']) ? $_GET['selected_date'] : '';
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

    // Obtém todos os pedidos (status: wc-processing)
    $orders = wc_get_orders(array(
        'status' => 'wc-processing',
        'm' => $date,
        'date_query' => array(
            'after' => $start_date,
            'before' => $end_date,
        ),
        'numberposts' => 400, // Recupera todos os pedidos
    ));

    $orders_data = array();

    foreach ($orders as $order) {
        $order_data = array(
            'order_id' => $order->get_id(),
            'order_status' => $order->get_status(),
            'order_date' => $order->get_date_created()->format('Y-m-d H:i:s'),
            'billing_info' => $order->get_formatted_billing_address(),
            'shipping_info' => $order->get_formatted_shipping_address(),
            'order_items' => array(),
        );

        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();

            // Obtém o código do produto
            $product_code = $product->get_sku(); // SKU é frequentemente usado como código do produto no WooCommerce

            $order_data['order_items'][] = array(
                'product_name' => $product->get_name(),
                'product_code' => $product_code,
                'quantity' => $item->get_quantity(),
                'price' => $product->get_price(),
                'subtotal' => $item->get_total(),
            );
        }

        $orders_data[] = $order_data;
    }

    // Envia os dados como JSON
    wp_send_json_success($orders_data);
}

add_action('wp_ajax_get_order_content', 'get_order_content');
add_action('wp_ajax_nopriv_get_order_content', 'get_order_content'); // Necessário se permitir solicitações de usuários não autenticados
?>
