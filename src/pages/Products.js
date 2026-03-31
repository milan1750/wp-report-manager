import { items } from "../data/mock";

export default function Products() {
  const productMap = {};

  items.forEach(i => {
    if (!productMap[i.product_title]) {
      productMap[i.product_title] = 0;
    }
    productMap[i.product_title] += i.quantity;
  });

  return (
    <div className="page">
      <h1>Product Performance</h1>

      <table className="table">
        <thead>
          <tr>
            <th>Product</th>
            <th>Qty Sold</th>
          </tr>
        </thead>

        <tbody>
          {Object.entries(productMap).map(([k, v]) => (
            <tr key={k}>
              <td>{k}</td>
              <td>{v}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
