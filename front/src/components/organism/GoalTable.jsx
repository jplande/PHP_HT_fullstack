// organisms/GoalTable.js
import React, { useState } from "react";
import Title from "../atoms/Title";
import {GoalRow } from "../molécules";

const GoalTable = ({onEdit, onDelete}) =>  { 
      const [goals, setGoals] = useState([
        {
            id: "4",
            title: "Faire du sport",
            description: "Courir 3 fois par semaine",
            status: "En cours",
        },
        {
            id: "5",
            title: "Lire un livre",
            description: "Terminer un chapitre par jour",
            status: "Non commencé",
        },
        {
            id: "6",
            title: "Apprendre React",
            description: "Suivre une formation",
            status: "Terminé",
        },
    ]);

    
    // const handleDelete = (id) => {
    //     console.log("Suppression du goal avec l'ID :", id);
    //     setGoals(goals.filter((goal) => goal.id !== id));
    // };

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
