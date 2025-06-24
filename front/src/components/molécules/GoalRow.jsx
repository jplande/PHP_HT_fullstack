import IconButton from "../atoms/IconButton";
import { faPencil, faTrash } from "@fortawesome/free-solid-svg-icons";

const GoalRow = ({ goal, index, onEdit, onDelete }) => {
    return (
        <tr>
            <th scope="row">{index + 1}</th>
            <td>{goal.title}</td>
            <td>{goal.description}</td>
            <td>{goal.status}</td>
            <td>
                <IconButton
                    icon={faPencil}
                    onClick={() => onEdit(goal)}
                    className="me-2"
                />
                <IconButton icon={faTrash} onClick={() => onDelete(goal.id)} />
            </td>
        </tr>
    );
};

export default GoalRow;
