// organisms/GoalTable.js
import Title from "../atoms/Title";
import {GoalRow } from "../molécules";

const GoalTable = ({ goals = [], onEdit, onDelete }) =>  { 
    return (
        
    <div className="container-fluide mt-4">
        <Title className="h1">Mes Objectifs</Title>
        <table className="table">
            <thead className="table-light">
                <tr>
                    <th>#</th>
                    <th>Objectifs</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                {goals.length === 0 ? (
                    <tr>
                        <td colSpan="5" className="text-center">
                            Aucun objectif pour le moment.
                        </td>
                    </tr>
                ) : (
                    goals.map((goal, index) => (
                        <GoalRow
                            key={goal.id}
                            goal={goal}
                            index={index}
                            onEdit={onEdit}
                            onDelete={onDelete}
                        />
                    ))
                )}
            </tbody>
        </table>
    </div>
    )
};

export default GoalTable;
