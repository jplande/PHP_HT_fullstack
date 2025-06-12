const GoalList = () => {
    return (
        <div>
            <h1>Goal List</h1>
            <table class="table">
                <thead class="table-light">
                  <tr>
                    <th scope="col">#</th>
                    <th scope="col">Objectifs</th>
                    <th scope="col">Description</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                  </tr>
                </thead>
              <tbody>
                  <tr>
                    <th scope="row">1</th>
                    <td>Objectif 1</td>
                    <td>Description de l'objectif 1</td>
                    <td>En cours</td>
                    <td>
                      <button class="btn btn-primary">Modifier</button>
                      <button class="btn btn-danger">Supprimer</button>
                    </td>
                  </tr>
                  <tr>
                    <th scope="row">2</th>
                    <td>Objectif 2</td>
                    <td>Description de l'objectif 2</td>
                    <td>Terminé</td>
                    <td>
                      <button class="btn btn-primary">Modifier</button>
                      <button class="btn btn-danger">Supprimer</button>
                    </td>
                  </tr>
              </tbody>
            </table>
        </div>
    );
};
export default GoalList;
