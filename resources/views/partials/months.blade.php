<div class="card">
  <div class="card-h"><h2>По месеци — таблица</h2></div>
  <div class="tbl-wrap"><table>
    <thead><tr><th>Месец</th><th>Заявки</th><th>Туристи</th><th>Оборот</th><th>Марж</th><th>Марж %</th><th>Средно на заявка</th><th>Оборот преди година</th><th>Промяна</th></tr></thead>
    <tbody>
    @foreach ($months as $m)
      <tr><td>{{ $m['label'] }}</td><td>@num($m['claims'])</td><td>@num($m['pax'])</td><td>@eur($m['sale'])</td><td>@eur($m['margin'])</td>
        <td>@pct($m['margin_pct'])</td><td>@eur($m['avg_sale'])</td><td>@eur($m['sale_ly'])</td><td>@delta($m['change_pct'])</td></tr>
    @endforeach
    <tr class="total"><td>Общо</td><td>@num(array_sum(array_column($months, 'claims')))</td><td>@num(array_sum(array_column($months, 'pax')))</td>
      <td>@eur(array_sum(array_column($months, 'sale')))</td><td>@eur(array_sum(array_column($months, 'margin')))</td><td></td><td></td>
      <td>@eur(array_sum(array_column($months, 'sale_ly')))</td>
      <td>@delta(\App\Support\Fmt::change(array_sum(array_column($months, 'sale')), array_sum(array_column($months, 'sale_ly'))))</td></tr>
    </tbody>
  </table></div>
</div>
